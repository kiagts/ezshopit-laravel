<?php

namespace App\Actions\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reviews\ReviewEligibilityService;
use App\Services\Reviews\ReviewerName;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductReview
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
        private readonly ReviewerName $reviewerName,
    ) {}

    public function handle(User $user, OrderItem $item, array $data): ProductReview
    {
        return DB::transaction(function () use ($user, $item, $data): ProductReview {
            $item = OrderItem::query()
                ->with(['vendorOrder.order', 'product'])
                ->lockForUpdate()
                ->findOrFail($item->id);

            if (! $this->eligibility->canReview($user, $item)) {
                $existing = $this->eligibility->existingReviewFor($user, $item);

                if ($existing) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'review' => 'This item is not eligible for review.',
                ]);
            }

            return ProductReview::query()->forceCreate([
                'user_id' => $user->id,
                'product_id' => $item->product_id,
                'order_item_id' => $item->id,
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'status' => ProductReviewStatus::Pending,
                'verified_purchase' => true,
                'reviewer_display_name' => $this->reviewerName->display($user),
                'variant_snapshot' => $item->variant_description,
                'submitted_at' => now(),
            ]);
        });
    }
}
