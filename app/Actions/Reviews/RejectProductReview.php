<?php

namespace App\Actions\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reviews\ProductRatingService;
use Illuminate\Support\Facades\DB;

class RejectProductReview
{
    public function __construct(private readonly ProductRatingService $ratings) {}

    public function handle(ProductReview $review, User $admin, string $reason): ProductReview
    {
        return DB::transaction(function () use ($review, $admin, $reason): ProductReview {
            $review = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($review->product_id)->lockForUpdate()->firstOrFail();

            if ($review->status === ProductReviewStatus::Approved) {
                $this->ratings->remove($product, $review->rating);
            }

            $review->forceFill([
                'status' => ProductReviewStatus::Rejected,
                'approved_at' => null,
                'rejected_at' => $review->rejected_at ?: now(),
                'moderated_by' => $admin->id,
                'moderation_reason' => $reason,
            ])->save();

            return $review;
        });
    }
}
