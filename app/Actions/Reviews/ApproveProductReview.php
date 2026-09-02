<?php

namespace App\Actions\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reviews\ProductRatingService;
use Illuminate\Support\Facades\DB;

class ApproveProductReview
{
    public function __construct(private readonly ProductRatingService $ratings) {}

    public function handle(ProductReview $review, User $admin): ProductReview
    {
        return DB::transaction(function () use ($review, $admin): ProductReview {
            $review = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($review->product_id)->lockForUpdate()->firstOrFail();

            if ($review->status !== ProductReviewStatus::Approved) {
                $this->ratings->add($product, $review->rating);
            }

            $review->forceFill([
                'status' => ProductReviewStatus::Approved,
                'approved_at' => $review->approved_at ?: now(),
                'rejected_at' => null,
                'moderated_by' => $admin->id,
                'moderation_reason' => null,
            ])->save();

            return $review;
        });
    }
}
