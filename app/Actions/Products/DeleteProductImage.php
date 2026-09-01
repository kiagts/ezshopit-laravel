<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteProductImage
{
    public function __construct(
        private readonly AddProductImages $guard,
        private readonly MarkProductContentChanged $markChanged,
    ) {}

    public function handle(Product $product, ProductImage $image): void
    {
        $this->guard->ensureEditable($product);
        abort_unless($image->product_id === $product->id, 404);

        $path = $image->path;
        $wasPrimary = $image->product_variant_id === null && $image->is_primary;
        $wasGeneral = $image->product_variant_id === null;

        DB::transaction(function () use ($product, $image, $wasPrimary, $wasGeneral): void {
            $image->delete();

            if ($wasPrimary) {
                $next = $product->galleryImages()->ordered()->first();
                $next?->forceFill(['is_primary' => true])->save();
            }

            if ($wasGeneral) {
                $this->normalize($product, null);
            } elseif ($image->product_variant_id) {
                $this->normalize($product, $image->product_variant_id);
            }

            $this->markChanged->handle($product);
        });

        Storage::disk(config('marketplace.product_media.disk', 'public'))->delete($path);
    }

    private function normalize(Product $product, ?int $variantId): void
    {
        $product->images()
            ->when($variantId, fn ($query) => $query->where('product_variant_id', $variantId), fn ($query) => $query->whereNull('product_variant_id'))
            ->ordered()
            ->get()
            ->each(fn (ProductImage $image, int $index) => $image->forceFill(['sort_order' => $index])->save());
    }
}
