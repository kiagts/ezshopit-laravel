<?php

namespace Database\Factories;

use App\Enums\ProductReviewStatus;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Services\Reviews\ReviewerName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    public function definition(): array
    {
        $item = OrderItem::factory()->create();
        $user = $item->vendorOrder->order->user;

        return [
            'user_id' => $user->id,
            'product_id' => $item->product_id,
            'order_item_id' => $item->id,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => ProductReviewStatus::Pending,
            'verified_purchase' => true,
            'reviewer_display_name' => app(ReviewerName::class)->display($user),
            'variant_snapshot' => $item->variant_description,
            'submitted_at' => now(),
        ];
    }
}
