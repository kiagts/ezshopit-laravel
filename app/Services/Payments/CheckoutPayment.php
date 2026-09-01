<?php

namespace App\Services\Payments;

use App\Services\Checkout\CheckoutSession;
use Illuminate\Http\Request;

class CheckoutPayment
{
    public function __construct(
        private readonly AvailablePaymentMethods $availablePaymentMethods,
        private readonly CheckoutSession $checkoutSession,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $methods = $this->availablePaymentMethods->all();
        $selected = $methods->first(fn (PaymentMethodOption $option): bool => $option->code() === $this->checkoutSession->paymentMethod($request));

        if (! $selected) {
            $selected = $methods->first();

            if ($selected) {
                $this->checkoutSession->putPaymentMethod($request, $selected->code());
            } else {
                $this->checkoutSession->forgetPaymentMethod($request);
            }
        }

        return [
            'payment_methods' => $methods,
            'selected_payment_method' => $selected,
            'payment_ready' => $selected !== null,
        ];
    }
}
