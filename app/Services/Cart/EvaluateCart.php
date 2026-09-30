<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Promotions\PromotionEvaluation;
use App\Services\Promotions\PromotionEvaluator;
use App\Services\TaxEngine;
use App\Support\Money;
use Illuminate\Support\Collection;

class EvaluateCart
{
    public function __construct(
        private readonly PromotionEvaluator $promotionEvaluator,
        private readonly TaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(?Cart $cart): array
    {
        if (! $cart) {
            return $this->empty();
        }

        $cart->load([
            'items.variant.inventory',
            'items.variant.images',
            'items.variant.attributeValues.productAttribute',
            'items.variant.product.vendor',
            'items.variant.product.primaryImage',
            'items.variant.product.firstGalleryImage',
            'items.variant.product.taxClass',
            'promotion.products',
            'promotion.categories',
            'user',
        ]);

        $publicProductIds = $this->publicProductIds($cart->items);
        $evaluatedItems = $cart->items
            ->sortBy('created_at')
            ->map(fn (CartItem $item): array => $this->evaluateItem($item, $publicProductIds))
            ->values();

        $promotion = $this->promotionEvaluator->evaluateApplied($cart, $evaluatedItems, $cart->user);
        $allocationsByItem = $promotion->allocations->keyBy('cart_item_id');
        $evaluatedItems = $evaluatedItems
            ->map(function (array $row) use ($allocationsByItem): array {
                $allocation = $allocationsByItem[(int) $row['item']->id] ?? null;
                $discount = (int) ($allocation['amount'] ?? 0);
                $isMarketplace = $allocation && $allocation['funding_source']->value === 'marketplace';
                $isVendor = $allocation && $allocation['funding_source']->value === 'vendor';

                $row['discount_total'] = $discount;
                $row['marketplace_discount_total'] = $isMarketplace ? $discount : 0;
                $row['vendor_discount_total'] = $isVendor ? $discount : 0;
                $row['net_line_total'] = max(0, (int) $row['line_total'] - $discount);
                $row['discount_total_formatted'] = Money::format($discount);
                $row['net_line_total_formatted'] = Money::format($row['net_line_total']);

                return $row;
            })
            ->values();

        $country = request()->user()?->addresses()->where('is_default', true)->value('country_code') ?: config('marketplace.checkout.default_country', 'PK');
        $evaluatedItems = $evaluatedItems->map(function (array $row) use ($country): array {
            $row['tax_total'] = $row['product'] ? $this->taxEngine->calculate(max(0, (int) $row['net_line_total']), $country, $row['product']->taxClass?->code)['tax_total'] : 0;
            return $row;
        });

        $groups = $evaluatedItems
            ->groupBy(fn (array $item): string => (string) ($item['vendor']?->id ?? 'unavailable'))
            ->map(function (Collection $items): array {
                $vendor = $items->first()['vendor'];

                return [
                    'vendor' => $vendor,
                    'items' => $items->values(),
                    'subtotal' => $items->sum('line_total'),
                    'discount_total' => $items->sum('discount_total'),
                    'marketplace_discount_total' => $items->sum('marketplace_discount_total'),
                    'vendor_discount_total' => $items->sum('vendor_discount_total'),
                    'net_subtotal' => $items->sum('net_line_total'),
                    'has_blocking_issue' => $items->contains(fn (array $item): bool => $item['status'] !== 'valid'),
                    'first_saved_at' => $items->min('created_at'),
                ];
            })
            ->sortBy('first_saved_at')
            ->values();

        $discountTotal = (int) $evaluatedItems->sum('discount_total');
        $subtotal = (int) $evaluatedItems->sum('line_total');
        $taxTotal = (int) $evaluatedItems->sum('tax_total');

        return [
            'cart' => $cart,
            'groups' => $groups,
            'items' => $evaluatedItems,
            'item_quantity' => $cart->items->sum('quantity'),
            'line_count' => $cart->items->count(),
            'subtotal' => $subtotal,
            'subtotal_formatted' => Money::format($subtotal),
            'discount_total' => $discountTotal,
            'discount_total_formatted' => Money::format($discountTotal),
            'total_after_discount' => max(0, $subtotal - $discountTotal),
            'total_after_discount_formatted' => Money::format(max(0, $subtotal - $discountTotal)),
            'tax_total' => $taxTotal,
            'tax_total_formatted' => Money::format($taxTotal),
            'grand_total' => max(0, $subtotal - $discountTotal) + $taxTotal,
            'grand_total_formatted' => Money::format(max(0, $subtotal - $discountTotal) + $taxTotal),
            'promotion_evaluation' => $promotion->toArray(),
            'has_blocking_issues' => $evaluatedItems->contains(fn (array $item): bool => $item['status'] !== 'valid'),
        ];
    }

    public function count(?Cart $cart): int
    {
        if (! $cart) {
            return 0;
        }

        return (int) $cart->items()->sum('quantity');
    }

    private function empty(): array
    {
        return [
            'cart' => null,
            'groups' => collect(),
            'items' => collect(),
            'item_quantity' => 0,
            'line_count' => 0,
            'subtotal' => 0,
            'subtotal_formatted' => Money::format(0),
            'discount_total' => 0,
            'discount_total_formatted' => Money::format(0),
            'total_after_discount' => 0,
            'total_after_discount_formatted' => Money::format(0),
            'promotion_evaluation' => PromotionEvaluation::none()->toArray(),
            'has_blocking_issues' => false,
        ];
    }

    private function publicProductIds(Collection $items): array
    {
        $productIds = $items
            ->map(fn (CartItem $item): ?int => $item->variant?->product_id)
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        return Product::query()
            ->publiclyVisible()
            ->whereIn('id', $productIds)
            ->pluck('id')
            ->all();
    }

    /**
     * @param array<int, int> $publicProductIds
     * @return array<string, mixed>
     */
    private function evaluateItem(CartItem $item, array $publicProductIds): array
    {
        $variant = $item->variant;
        $product = $variant?->product;
        $isProductPublic = $product && in_array($product->id, $publicProductIds, true);
        $price = $variant?->storefrontPrice();
        $effectivePrice = $price['effective'] ?? null;
        $available = $variant?->availableQuantity() ?? 0;
        $status = 'valid';
        $message = null;
        $canShowProduct = $isProductPublic;
        $canPurchase = true;

        if (! $variant || ! $product || ! $isProductPublic) {
            $status = 'product_unavailable';
            $message = 'This item is no longer available.';
            $canShowProduct = false;
            $canPurchase = false;
            $effectivePrice = null;
        } elseif (! $variant->is_active || $variant->trashed()) {
            $status = 'variant_unavailable';
            $message = 'This option is no longer available.';
            $canPurchase = false;
            $effectivePrice = null;
        } elseif (! $variant->inventory || $available <= 0) {
            $status = 'out_of_stock';
            $message = 'Out of Stock';
            $canPurchase = false;
        } elseif ($item->quantity > $available) {
            $status = 'insufficient_stock';
            $message = 'Requested quantity is no longer available.';
            $canPurchase = false;
        }

        $lineTotal = $effectivePrice ? $effectivePrice * $item->quantity : 0;

        return [
            'item' => $item,
            'product' => $canShowProduct ? $product : null,
            'variant' => $variant,
            'vendor' => $canShowProduct ? $product->vendor : null,
            'status' => $status,
            'message' => $message,
            'can_purchase' => $canPurchase,
            'can_show_product' => $canShowProduct,
            'price' => $price,
            'unit_price' => $effectivePrice,
            'unit_price_formatted' => $effectivePrice ? Money::format($effectivePrice) : null,
            'line_total' => $lineTotal,
            'line_total_formatted' => Money::format($lineTotal),
            'quantity' => $item->quantity,
            'available_quantity' => $available,
            'variant_label' => $this->variantLabel($variant),
            'image' => $this->image($variant),
            'created_at' => $item->created_at,
        ];
    }

    private function variantLabel(?ProductVariant $variant): ?string
    {
        if (! $variant || $variant->is_default || $variant->combination_key === 'default') {
            return null;
        }

        return $variant->displayName();
    }

    private function image(?ProductVariant $variant): ?ProductImage
    {
        if (! $variant) {
            return null;
        }

        return $variant->images->sortBy(fn (ProductImage $image): array => [$image->sort_order, $image->id])->first()
            ?: $variant->product?->displayImage();
    }
}
