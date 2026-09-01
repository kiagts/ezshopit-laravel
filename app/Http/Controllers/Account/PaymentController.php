<?php

namespace App\Http\Controllers\Account;

use App\Actions\Payments\CanRetryCardPayment;
use App\Actions\Payments\InitializeOnlinePayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function retry(Request $request, Order $order, CanRetryCardPayment $canRetryPayment, InitializeOnlinePayment $initializePayment): JsonResponse|RedirectResponse
    {
        $this->authorizeOwned($request, $order);

        $eligibility = $canRetryPayment->check($order);

        if (! $eligibility->allowed) {
            throw ValidationException::withMessages(['payment' => $eligibility->message ?? 'Payment cannot be retried right now.']);
        }

        $result = $initializePayment->handle($order->payment);
        $payload = [
            'order_number' => $order->order_number,
            'client_secret' => $result->clientSecret,
            'return_url' => route('account.orders.payment.return', $order),
            'publishable_key' => config('payments.stripe.public_key'),
            'payment_status_url' => route('account.orders.payment.status', $order),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('status', 'Secure card payment is ready. Use the card payment form to complete it.');
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwned($request, $order);
        $order->loadMissing('payment');

        return response()->json([
            'order_number' => $order->order_number,
            'payment_status' => ($order->payment?->status ?? $order->payment_status)->value,
            'payment_status_label' => ($order->payment?->status ?? $order->payment_status)->label(),
            'paid' => ($order->payment?->status ?? $order->payment_status) === PaymentStatus::Paid,
        ]);
    }

    public function return(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwned($request, $order);

        return redirect()
            ->route('account.orders.show', $order)
            ->with('status', 'Payment status is being confirmed securely. This page will update after Stripe notifies us.');
    }

    private function authorizeOwned(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
