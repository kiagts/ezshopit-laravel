<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reviews\ReviewEligibilityService;

class ProductReviewPolicy
{
    public function create(User $user, OrderItem $orderItem): bool
    {
        return $user->isCustomer()
            && app(ReviewEligibilityService::class)->canReview($user, $orderItem);
    }

    public function view(User $user, ProductReview $review): bool
    {
        return $user->isAdmin()
            || (int) $review->user_id === (int) $user->id
            || ($user->isVendor() && (int) $review->product?->vendor_id === (int) $user->vendor?->id);
    }

    public function update(User $user, ProductReview $review): bool
    {
        return $user->isCustomer() && (int) $review->user_id === (int) $user->id;
    }

    public function moderate(User $user, ProductReview $review): bool
    {
        return $user->isAdmin();
    }
}
