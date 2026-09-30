<?php

namespace App\Services\Promotions;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFundingSource;
use App\Models\Category;
use App\Models\Cart;
use App\Models\Promotion;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

class PromotionEvaluator
{
    public function evaluateApplied(Cart $cart, Collection $items, ?User $user = null): PromotionEvaluation
    {
        if (! $cart->promotion_id) {
            return PromotionEvaluation::none();
        }

        $promotion = $cart->relationLoaded('promotion')
            ? $cart->promotion
            : $cart->promotion()->first();

        if (! $promotion) {
            return PromotionEvaluation::invalid(null, 'This coupon is no longer available.');
        }

        return $this->evaluate($promotion, $items, $user ?? $cart->user);
    }

    public function evaluateCode(string $code, Collection $items, ?User $user = null): PromotionEvaluation
    {
        $normalized = PromotionCode::normalize($code);

        if ($normalized === '') {
            return PromotionEvaluation::invalid(null, 'Enter a coupon code.');
        }

        $promotion = Promotion::query()
            ->with(['products:id', 'categories:id'])
            ->where('code', $normalized)
            ->first();

        if (! $promotion) {
            return PromotionEvaluation::invalid(null, 'We could not find that coupon.');
        }

        return $this->evaluate($promotion, $items, $user);
    }

    public function evaluate(Promotion $promotion, Collection $items, ?User $user = null): PromotionEvaluation
    {
        $promotion->loadMissing(['products:id', 'categories:id']);

        if (! $promotion->is_active) {
            return PromotionEvaluation::invalid($promotion, 'This coupon is not active.');
        }

        $now = now();

        if ($promotion->starts_at && $promotion->starts_at->greaterThan($now)) {
            return PromotionEvaluation::invalid($promotion, 'This coupon is not active yet.');
        }

        if ($promotion->ends_at && $promotion->ends_at->lessThanOrEqualTo($now)) {
            return PromotionEvaluation::invalid($promotion, 'This coupon has expired.');
        }

        if ($promotion->usage_limit !== null && $promotion->redemptions()->count() >= $promotion->usage_limit) {
            return PromotionEvaluation::invalid($promotion, 'This coupon has reached its usage limit.');
        }

        if ($user && $promotion->per_customer_limit !== null) {
            $userUses = $promotion->redemptions()->where('user_id', $user->id)->count();

            if ($userUses >= $promotion->per_customer_limit) {
                return PromotionEvaluation::invalid($promotion, 'You have already used this coupon.');
            }
        }

        $eligibleItems = $this->eligibleItems($promotion, $items);
        $eligibleSubtotal = (int) $eligibleItems->sum('line_total');

        if ($eligibleSubtotal <= 0) {
            return PromotionEvaluation::invalid($promotion, 'This coupon does not apply to the items in your cart.');
        }

        if ($promotion->minimum_spend !== null && $eligibleSubtotal < $promotion->minimum_spend) {
            return PromotionEvaluation::invalid($promotion, 'Add eligible items worth at least '.Money::format($promotion->minimum_spend).' to use this coupon.');
        }

        $discount = $this->discountAmount($promotion, $eligibleSubtotal);

        if ($discount <= 0) {
            return PromotionEvaluation::invalid($promotion, 'This coupon does not reduce the current cart total.');
        }

        return new PromotionEvaluation(
            $promotion,
            true,
            'Coupon applied.',
            $eligibleSubtotal,
            $discount,
            $this->allocate($promotion, $eligibleItems, $discount, $eligibleSubtotal),
        );
    }

    private function eligibleItems(Promotion $promotion, Collection $items): Collection
    {
        $validItems = $items->filter(fn (array $row): bool => ($row['status'] ?? null) === 'valid' && (int) ($row['line_total'] ?? 0) > 0);

        if ($promotion->funding_source === PromotionFundingSource::Vendor) {
            $validItems = $validItems->filter(fn (array $row): bool => (int) ($row['vendor']?->id ?? 0) === (int) $promotion->vendor_id);
        }

        $productIds = $promotion->products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $categoryIds = $this->categoryScopeIds($promotion);

        if ($productIds === [] && $categoryIds === []) {
            return $validItems->values();
        }

        return $validItems
            ->filter(function (array $row) use ($productIds, $categoryIds): bool {
                $product = $row['product'] ?? null;

                if (! $product) {
                    return false;
                }

                return in_array((int) $product->id, $productIds, true)
                    || in_array((int) $product->category_id, $categoryIds, true);
            })
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function categoryScopeIds(Promotion $promotion): array
    {
        $ids = $promotion->categories->pluck('id')->map(fn ($id): int => (int) $id);

        if ($ids->isEmpty()) {
            return [];
        }

        $descendants = Category::query()
            ->whereIn('id', $ids)
            ->get()
            ->flatMap(fn (Category $category): Collection => $category->descendantIds());

        return $ids->merge($descendants)->unique()->values()->all();
    }

    private function discountAmount(Promotion $promotion, int $eligibleSubtotal): int
    {
        $discount = match ($promotion->discount_type) {
            PromotionDiscountType::Percentage => intdiv($eligibleSubtotal * (int) $promotion->discount_value, 10000),
            PromotionDiscountType::FixedAmount => min((int) $promotion->discount_value, $eligibleSubtotal),
        };

        if ($promotion->maximum_discount !== null) {
            $discount = min($discount, (int) $promotion->maximum_discount);
        }

        return min($discount, $eligibleSubtotal);
    }

    private function allocate(Promotion $promotion, Collection $eligibleItems, int $discount, int $eligibleSubtotal): Collection
    {
        $rows = $eligibleItems
            ->map(function (array $row) use ($promotion, $discount, $eligibleSubtotal): array {
                $lineTotal = (int) $row['line_total'];
                $numerator = $discount * $lineTotal;

                return [
                    'cart_item_id' => (int) $row['item']->id,
                    'vendor_id' => (int) $row['vendor']->id,
                    'product_id' => (int) $row['product']->id,
                    'amount' => intdiv($numerator, $eligibleSubtotal),
                    'remainder' => $numerator % $eligibleSubtotal,
                    'line_total' => $lineTotal,
                    'funding_source' => $promotion->funding_source,
                ];
            })
            ->values();

        $remaining = $discount - (int) $rows->sum('amount');

        return $rows
            ->sortBy([
                ['remainder', 'desc'],
                ['line_total', 'desc'],
                ['cart_item_id', 'asc'],
            ])
            ->values()
            ->map(function (array $row, int $index) use ($remaining): array {
                if ($index < $remaining) {
                    $row['amount']++;
                }

                unset($row['remainder'], $row['line_total']);

                return $row;
            })
            ->filter(fn (array $row): bool => (int) $row['amount'] > 0)
            ->sortBy('cart_item_id')
            ->values();
    }
}
