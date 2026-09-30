<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use App\Services\Cart\CartResolver;
use App\Services\Cart\EvaluateCart;
use App\Services\Promotions\PromotionEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MergeGuestCart
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly EvaluateCart $evaluateCart,
        private readonly PromotionEvaluator $promotionEvaluator,
    ) {}

    public function handle(Request $request, User $user): void
    {
        if (! $user->isCustomer()) {
            return;
        }

        $token = $this->cartResolver->token($request);

        if (! $token) {
            return;
        }

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where('token', $token)
            ->with('items')
            ->first();

        if (! $guestCart) {
            $this->cartResolver->forgetGuestToken($request);

            return;
        }

        DB::transaction(function () use ($guestCart, $user): void {
            $customerCart = Cart::query()->firstOrCreate(['user_id' => $user->id], ['token' => null]);
            $max = max(1, (int) config('marketplace.cart.max_quantity_per_item', 20));
            $promotionId = $guestCart->promotion_id ?: $customerCart->promotion_id;

            foreach ($guestCart->items as $guestItem) {
                $existing = $customerCart->items()
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->quantity = min($max, $existing->quantity + $guestItem->quantity);
                    $existing->save();

                    continue;
                }

                $customerCart->items()->create([
                    'product_variant_id' => $guestItem->product_variant_id,
                    'quantity' => min($max, $guestItem->quantity),
                ]);
            }

            if ($promotionId) {
                $customerCart->forceFill(['promotion_id' => $promotionId])->save();
                $evaluation = $this->evaluateCart->evaluate($customerCart->fresh(['promotion']));
                $promotionEvaluation = $this->promotionEvaluator->evaluateApplied($customerCart->fresh(['promotion']), $evaluation['items'], $user);

                if (! $promotionEvaluation->valid) {
                    $customerCart->forceFill(['promotion_id' => null])->save();
                }
            }

            $guestCart->delete();
        });

        $this->cartResolver->forgetGuestToken($request);
    }
}
