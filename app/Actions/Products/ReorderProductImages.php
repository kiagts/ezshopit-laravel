<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderProductImages
{
    public function __construct(
        private readonly AddProductImages $guard,
        private readonly MarkProductContentChanged $markChanged,
    ) {}

    /**
     * @param array<int, int|string> $orderedImageIds
     */
    public function handle(Product $product, array $orderedImageIds): void
    {
        $this->guard->ensureEditable($product);
        $ids = collect($orderedImageIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $images = $product->images()->whereIn('id', $ids)->get()->keyBy('id');

        if ($images->count() !== $ids->count()) {
            throw ValidationException::withMessages(['images' => 'All reordered images must belong to this product.']);
        }

        if ($images->contains(fn ($image): bool => $image->product_variant_id !== null)) {
            throw ValidationException::withMessages(['images' => 'Only product gallery images can be reordered here.']);
        }

        DB::transaction(function () use ($product, $ids): void {
            foreach ($ids as $index => $imageId) {
                $product->images()->whereKey($imageId)->update(['sort_order' => $index]);
            }

            $this->markChanged->handle($product);
        });
    }
}
