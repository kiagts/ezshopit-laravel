<?php

namespace App\Services\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Support\Facades\DB;

class ProductRatingService
{
    public function add(Product $product, int $rating): void
    {
        $product->forceFill([
            'rating_sum' => max(0, (int) $product->rating_sum + $rating),
            'rating_count' => max(0, (int) $product->rating_count + 1),
        ])->save();
    }

    public function remove(Product $product, int $rating): void
    {
        $product->forceFill([
            'rating_sum' => max(0, (int) $product->rating_sum - $rating),
            'rating_count' => max(0, (int) $product->rating_count - 1),
        ])->save();
    }

    public function rebuild(?Product $product = null): void
    {
        $query = $product ? Product::query()->whereKey($product->id) : Product::query();

        $query->orderBy('id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                DB::transaction(function () use ($product): void {
                    $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                    $summary = ProductReview::query()
                        ->where('product_id', $product->id)
                        ->where('status', ProductReviewStatus::Approved)
                        ->selectRaw('COALESCE(SUM(rating), 0) as rating_sum, COUNT(*) as rating_count')
                        ->first();

                    $product->forceFill([
                        'rating_sum' => (int) $summary->rating_sum,
                        'rating_count' => (int) $summary->rating_count,
                    ])->save();
                });
            }
        });
    }

    public function distribution(Product $product): array
    {
        $counts = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved)
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $total = max(0, (int) $counts->sum());

        return collect(range(5, 1))
            ->mapWithKeys(function (int $rating) use ($counts, $total): array {
                $count = (int) ($counts[$rating] ?? 0);

                return [$rating => [
                    'count' => $count,
                    'percentage' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
                ]];
            })
            ->all();
    }
}
