<?php

namespace App\Actions\Checkout;

use App\Services\Cart\CartResolver;
use App\Services\Cart\EvaluateCart;
use App\Services\Checkout\CheckoutSession;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class PrepareCheckout
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly EvaluateCart $evaluateCart,
        private readonly CheckoutSession $checkoutSession,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Request $request): array
    {
        $evaluation = $this->evaluate($request);

        if ($evaluation['line_count'] === 0) {
            $this->checkoutSession->clear($request);

            throw new HttpResponseException(
                redirect()->route('cart.index')->with('status', 'Your cart is empty.')
            );
        }

        if ($evaluation['has_blocking_issues']) {
            throw new HttpResponseException(
                redirect()->route('cart.index')->with('status', 'Some items in your cart need attention before checkout.')
            );
        }

        return $evaluation;
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Request $request): array
    {
        return $this->evaluateCart->evaluate($this->cartResolver->current($request));
    }
}
