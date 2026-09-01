<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Collection;

class EvaluateCart
{
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
        ]);

        $publicProductIds = $this->publicProductIds($cart->items);
        $evaluatedItems = $cart->items
            ->sortBy('created_at')
            ->map(fn (CartItem $item): array => $this->evaluateItem($item, $publicProductIds))
            ->values();

        $groups = $evaluatedItems
            ->groupBy(fn (array $item): string => (string) ($item['vendor']?->id ?? 'unavailable'))
            ->map(function (Collection $items): array {
                $vendor = $items->first()['vendor'];

                return [
                    'vendor' => $vendor,
                    'items' => $items->values(),
                    'subtotal' => $items->sum('line_total'),
                    'has_blocking_issue' => $items->contains(fn (array $item): bool => $item['status'] !== 'valid'),
                    'first_saved_at' => $items->min('created_at'),
                ];
            })
            ->sortBy('first_saved_at')
            ->values();

        return [
            'cart' => $cart,
            'groups' => $groups,
            'items' => $evaluatedItems,
            'item_quantity' => $cart->items->sum('quantity'),
            'line_count' => $cart->items->count(),
            'subtotal' => $evaluatedItems->sum('line_total'),
            'subtotal_formatted' => Money::format($evaluatedItems->sum('line_total')),
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
