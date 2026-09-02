<?php

namespace App\Services\Reviews;

use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReviewEligibilityService
{
    public function canReview(User $user, OrderItem $item): bool
    {
        $item->loadMissing(['vendorOrder.order', 'product']);

        return $item->product !== null
            && $item->vendorOrder?->status === VendorOrderStatus::Delivered
            && (int) $item->vendorOrder?->order?->user_id === (int) $user->id
            && ! ProductReview::query()
                ->where('user_id', $user->id)
                ->where('product_id', $item->product_id)
                ->exists();
    }

    public function existingReviewFor(User $user, OrderItem|Product $subject): ?ProductReview
    {
        $productId = $subject instanceof Product ? $subject->id : $subject->product_id;

        if (! $productId) {
            return null;
        }

        return ProductReview::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();
    }

    public function awaitingReviewItems(User $user): Collection
    {
        return OrderItem::query()
            ->with(['product.primaryImage', 'product.firstGalleryImage', 'vendorOrder.order'])
            ->whereNotNull('product_id')
            ->whereHas('vendorOrder', function (Builder $query) use ($user): void {
                $query
                    ->where('status', VendorOrderStatus::Delivered)
                    ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id));
            })
            ->whereDoesntHave('product.reviews', fn (Builder $query) => $query->where('user_id', $user->id))
            ->latest('id')
            ->get()
            ->unique('product_id')
            ->values();
    }
}
