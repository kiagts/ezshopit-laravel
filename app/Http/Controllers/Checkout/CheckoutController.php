<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\PrepareCheckout;
use App\Actions\CustomerAddresses\StoreCustomerAddress;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Payments\InitializeOnlinePayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreCustomerAddressRequest;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Requests\Checkout\SelectCheckoutAddressRequest;
use App\Http\Requests\Checkout\UpdateCheckoutPaymentRequest;
use App\Http\Requests\Checkout\UpdateCheckoutShippingRequest;
use App\Services\Checkout\CheckoutSession;
use App\Services\Payments\AvailablePaymentMethods;
use App\Services\Payments\CheckoutPayment;
use App\Services\Shipping\CheckoutShipping;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request, PrepareCheckout $prepareCheckout, CheckoutSession $checkoutSession, CheckoutShipping $checkoutShipping, CheckoutPayment $checkoutPayment): View
    {
        $evaluation = $prepareCheckout->handle($request);
        $addresses = $this->addresses($request);
        $selectedAddress = $checkoutSession->selectedAddress($request, $addresses);
        $shippingData = $checkoutShipping->build($request, $evaluation, $selectedAddress);
        $paymentData = $checkoutPayment->build($request);
        $checkoutReady = $shippingData['shipping_ready'] && $paymentData['payment_ready'];

        return view('checkout.index', array_merge($evaluation, $shippingData, $paymentData, [
            'addresses' => $addresses,
            'selectedAddress' => $selectedAddress,
            'countries' => Countries::supported(),
            'checkoutReady' => $checkoutReady,
            'idempotencyKey' => $selectedAddress && $checkoutReady
                ? $this->idempotencyKey($request, $checkoutSession, $shippingData, $evaluation, $selectedAddress)
                : null,
        ]));
    }

    public function updateAddress(SelectCheckoutAddressRequest $request, PrepareCheckout $prepareCheckout, CheckoutSession $checkoutSession): RedirectResponse
    {
        $prepareCheckout->handle($request);

        $address = $request->user()->addresses()->whereKey((int) $request->validated('address_id'))->firstOrFail();
        $checkoutSession->selectAddress($request, $address);

        return redirect()->route('checkout.index')->with('status', 'Delivery address selected.');
    }

    public function storeAddress(StoreCustomerAddressRequest $request, PrepareCheckout $prepareCheckout, StoreCustomerAddress $storeAddress, CheckoutSession $checkoutSession): RedirectResponse
    {
        $prepareCheckout->handle($request);

        $address = $storeAddress->handle($request->user(), $request->validated());
        $checkoutSession->selectAddress($request, $address);

        return redirect()->route('checkout.index')->with('status', 'Delivery address saved.');
    }

    public function updateShipping(UpdateCheckoutShippingRequest $request, PrepareCheckout $prepareCheckout, CheckoutSession $checkoutSession, CheckoutShipping $checkoutShipping): RedirectResponse
    {
        $evaluation = $prepareCheckout->handle($request);
        $addresses = $this->addresses($request);
        $selectedAddress = $checkoutSession->selectedAddress($request, $addresses);

        if (! $selectedAddress) {
            throw ValidationException::withMessages([
                'shipping_methods' => 'Select a delivery address before choosing delivery options.',
            ]);
        }

        $shippingData = $checkoutShipping->build($request, $evaluation, $selectedAddress);
        $submitted = collect($request->validated('shipping_methods'))->mapWithKeys(
            fn ($methodId, $vendorId): array => [(int) $vendorId => (int) $methodId],
        );
        $resolved = [];

        foreach ($shippingData['shipping_groups'] as $group) {
            $vendorId = $group['vendor']->id;
            $methodId = $submitted[$vendorId] ?? null;
            $method = $group['available_shipping_methods']->firstWhere('id', $methodId);

            if (! $method) {
                throw ValidationException::withMessages([
                    "shipping_methods.{$vendorId}" => "Choose an available delivery method for {$group['vendor']->display_name}.",
                ]);
            }

            $resolved[$vendorId] = $method->id;
        }

        $checkoutSession->putShippingMethods($request, $resolved);

        return redirect()->route('checkout.index')->with('status', 'Delivery options updated.');
    }

    public function updatePayment(UpdateCheckoutPaymentRequest $request, PrepareCheckout $prepareCheckout, CheckoutSession $checkoutSession, AvailablePaymentMethods $availablePaymentMethods): RedirectResponse
    {
        $prepareCheckout->handle($request);

        $method = $availablePaymentMethods->find((string) $request->validated('payment_method'));

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose an available payment method.',
            ]);
        }

        $checkoutSession->putPaymentMethod($request, $method->code());

        return redirect()->route('checkout.index')->with('status', 'Payment method updated.');
    }

    public function placeOrder(PlaceOrderRequest $request, PlaceOrder $placeOrder, InitializeOnlinePayment $initializePayment): RedirectResponse|JsonResponse
    {
        try {
            $order = $placeOrder->handle($request, (string) $request->validated('idempotency_key'));
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => collect($exception->errors())->flatten()->first()], 422);
            }

            return redirect()->route('checkout.index')->withErrors($exception->errors());
        }

        $order->load('payment');

        if ($order->payment?->method === PaymentMethod::Card) {
            try {
                $result = $initializePayment->handle($order->payment);
            } catch (ValidationException $exception) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => collect($exception->errors())->flatten()->first(),
                        'order_number' => $order->order_number,
                        'order_url' => route('account.orders.show', $order),
                    ], 422);
                }

                return redirect()
                    ->route('account.orders.show', $order)
                    ->withErrors($exception->errors());
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'order_number' => $order->order_number,
                    'client_secret' => $result->clientSecret,
                    'return_url' => route('account.orders.payment.return', $order),
                    'publishable_key' => config('payments.stripe.public_key'),
                    'payment_status_url' => route('account.orders.payment.status', $order),
                ]);
            }

            return redirect()
                ->route('account.orders.show', $order)
                ->with('status', 'Order placed. Complete secure card payment to release it for fulfillment.');
        }

        return redirect()->route('account.orders.success', $order)->with('status', 'Order placed.');
    }

    private function idempotencyKey(Request $request, CheckoutSession $checkoutSession, array $shippingData, array $evaluation, $selectedAddress): ?string
    {
        if (! $shippingData['shipping_ready']) {
            return null;
        }

        return $checkoutSession->idempotencyKey($request, $checkoutSession->signature($evaluation, $selectedAddress, $shippingData, $checkoutSession->paymentMethod($request)));
    }

    private function addresses(Request $request)
    {
        return $request->user()->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }
}
