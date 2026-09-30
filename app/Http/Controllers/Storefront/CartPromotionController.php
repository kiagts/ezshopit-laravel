<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartResolver;
use App\Services\Cart\EvaluateCart;
use App\Services\Promotions\PromotionEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartPromotionController extends Controller
{
    public function store(Request $request, CartResolver $cartResolver, EvaluateCart $evaluateCart, PromotionEvaluator $promotionEvaluator): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $cart = $cartResolver->current($request, create: true);
        $evaluation = $evaluateCart->evaluate($cart);

        if ($evaluation['line_count'] === 0) {
            return back()->withErrors(['code' => 'Add items to your cart before applying a coupon.']);
        }

        $promotionEvaluation = $promotionEvaluator->evaluateCode((string) $validated['code'], $evaluation['items'], $request->user());

        if (! $promotionEvaluation->valid || ! $promotionEvaluation->promotion) {
            return back()->withErrors(['code' => $promotionEvaluation->message ?? 'This coupon cannot be applied.']);
        }

        $cart->forceFill(['promotion_id' => $promotionEvaluation->promotion->id])->save();

        return back()->with('status', 'Coupon applied.');
    }

    public function destroy(Request $request, CartResolver $cartResolver): RedirectResponse
    {
        $cart = $cartResolver->current($request);
        $cart?->forceFill(['promotion_id' => null])->save();

        return back()->with('status', 'Coupon removed.');
    }
}
