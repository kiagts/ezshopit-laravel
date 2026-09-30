<?php

namespace App\Actions\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\Reviews\ProductRatingService;
use Illuminate\Support\Facades\DB;

class UpdateProductReview
{
    public function __construct(
        private readonly ProductRatingService $ratings,
        private readonly MarketplaceNotifier $notifier,
    ) {}

    public function handle(ProductReview $review, array $data): ProductReview
    {
        $review = DB::transaction(function () use ($review, $data): ProductReview {
            $review = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($review->product_id)->lockForUpdate()->firstOrFail();

            if ($review->status === ProductReviewStatus::Approved) {
                $this->ratings->remove($product, $review->rating);
            }

            $review->forceFill([
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'status' => ProductReviewStatus::Pending,
                'approved_at' => null,
                'rejected_at' => null,
                'moderated_by' => null,
                'moderation_reason' => null,
                'submitted_at' => now(),
            ])->save();

            return $review;
        });

        $this->notifier->reviewSubmitted($review);

        return $review;
    }
}
