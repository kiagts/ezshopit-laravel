<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;

class WishlistStateService
{
    /**
     * @param iterable<int, Product> $products
     * @return array<int, int>
     */
    public function idsForProducts(?User $user, iterable $products): array
    {
        if (! $user?->isCustomer()) {
            return [];
        }

        $productIds = collect($products)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        return WishlistItem::query()
            ->where('user_id', $user->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();
    }

    public function isWishlisted(?User $user, Product $product): bool
    {
        if (! $user?->isCustomer()) {
            return false;
        }

        return WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();
    }

    public function countForUser(?User $user): int
    {
        if (! $user?->isCustomer()) {
            return 0;
        }

        return WishlistItem::query()
            ->where('user_id', $user->id)
            ->count();
    }

    /**
     * @param Collection<int, WishlistItem> $items
     * @return array<int, int>
     */
    public function publicProductIdsForItems(Collection $items): array
    {
        $productIds = $items
            ->pluck('product_id')
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
}
