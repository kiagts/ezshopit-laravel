<?php

namespace App\Services\Checkout;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CheckoutSession
{
    public const ADDRESS_KEY = 'checkout.address_id';
    public const SHIPPING_METHODS_KEY = 'checkout.shipping_methods';
    public const PAYMENT_METHOD_KEY = 'checkout.payment_method';
    public const IDEMPOTENCY_KEY = 'checkout.idempotency_key';
    public const SIGNATURE_KEY = 'checkout.signature';
    public const GIFT_CARD_KEY = 'checkout.gift_card';

    public function selectedAddress(Request $request, Collection $addresses): ?CustomerAddress
    {
        $selectedId = $request->session()->get(self::ADDRESS_KEY);

        if ($selectedId) {
            $selected = $addresses->firstWhere('id', (int) $selectedId);

            if ($selected) {
                return $selected;
            }

            $this->forgetAddress($request);
        }

        return $addresses->firstWhere('is_default', true) ?: $addresses->first();
    }

    public function selectAddress(Request $request, CustomerAddress $address): void
    {
        $request->session()->put(self::ADDRESS_KEY, $address->id);
    }

    public function forgetAddress(Request $request): void
    {
        $request->session()->forget(self::ADDRESS_KEY);
        $request->session()->forget(self::SHIPPING_METHODS_KEY);
        $this->forgetIdempotency($request);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget('checkout');
    }

    public function idempotencyKey(Request $request, string $signature): string
    {
        if ($request->session()->get(self::SIGNATURE_KEY) !== $signature) {
            $request->session()->put(self::SIGNATURE_KEY, $signature);
            $request->session()->put(self::IDEMPOTENCY_KEY, (string) Str::uuid());
        }

        if (! $request->session()->has(self::IDEMPOTENCY_KEY)) {
            $request->session()->put(self::IDEMPOTENCY_KEY, (string) Str::uuid());
        }

        return (string) $request->session()->get(self::IDEMPOTENCY_KEY);
    }

    /**
     * @param array<string, mixed> $cartEvaluation
     * @param array<string, mixed> $shippingData
     */
    public function signature(array $cartEvaluation, CustomerAddress $address, array $shippingData, ?string $paymentMethod): string
    {
        return hash('sha256', json_encode([
            'cart' => $cartEvaluation['items']
                ->map(fn (array $row): array => [
                    'cart_item_id' => $row['item']->id,
                    'variant_id' => $row['variant']->id,
                    'quantity' => $row['quantity'],
                ])
                ->sortBy('cart_item_id')
                ->values()
                ->all(),
            'promotion' => [
                'id' => $cartEvaluation['promotion_evaluation']['promotion']?->id,
                'code' => $cartEvaluation['promotion_evaluation']['promotion']?->code,
                'discount_total' => (int) ($cartEvaluation['discount_total'] ?? 0),
            ],
            'address_id' => $address->id,
            'shipping' => collect($shippingData['shipping_groups'])
                ->map(fn (array $group): array => [
                    'vendor_id' => $group['vendor']->id,
                    'shipping_method_id' => $group['selected_shipping_method']?->id,
                ])
                ->sortBy('vendor_id')
                ->values()
                ->all(),
            'payment_method' => $paymentMethod,
            'wallet_selected' => (bool) request()->session()->get('checkout.use_wallet', false),
            'gift_card' => request()->session()->get(self::GIFT_CARD_KEY),
            'currency' => app(\App\Services\CurrencyContext::class)->current(request())->code,
        ]));
    }

    public function paymentMethod(Request $request): ?string
    {
        $method = $request->session()->get(self::PAYMENT_METHOD_KEY);

        return is_string($method) ? $method : null;
    }

    public function putPaymentMethod(Request $request, string $method): void
    {
        if ($this->paymentMethod($request) !== $method) {
            $request->session()->put(self::PAYMENT_METHOD_KEY, $method);
            $this->forgetIdempotency($request);
        }
    }

    public function forgetPaymentMethod(Request $request): void
    {
        $request->session()->forget(self::PAYMENT_METHOD_KEY);
        $this->forgetIdempotency($request);
    }

    /**
     * @return array<int, int>
     */
    public function shippingMethods(Request $request): array
    {
        return collect($request->session()->get(self::SHIPPING_METHODS_KEY, []))
            ->mapWithKeys(fn ($methodId, $vendorId): array => [(int) $vendorId => (int) $methodId])
            ->all();
    }

    /**
     * @param array<int, int> $selections
     */
    public function putShippingMethods(Request $request, array $selections): void
    {
        $request->session()->put(self::SHIPPING_METHODS_KEY, collect($selections)
            ->mapWithKeys(fn (int $methodId, int $vendorId): array => [(string) $vendorId => $methodId])
            ->all());
        $this->forgetIdempotency($request);
    }

    /**
     * @param array<int, int> $currentVendorIds
     * @param array<int, int> $resolvedSelections
     */
    public function syncShippingMethods(Request $request, array $currentVendorIds, array $resolvedSelections): void
    {
        $currentVendorIds = array_map('intval', $currentVendorIds);

        $synced = collect($resolvedSelections)
            ->filter(fn (int $methodId, int $vendorId): bool => in_array((int) $vendorId, $currentVendorIds, true))
            ->all();

        if ($this->shippingMethods($request) !== $synced) {
            $this->putShippingMethods($request, $synced);
        }
    }

    private function forgetIdempotency(Request $request): void
    {
        $request->session()->forget([self::IDEMPOTENCY_KEY, self::SIGNATURE_KEY]);
    }
}
