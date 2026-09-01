<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetPrimaryProductImage
{
    public function __construct(
        private readonly AddProductImages $guard,
        private readonly MarkProductContentChanged $markChanged,
    ) {}

    public function handle(Product $product, ProductImage $image): ProductImage
    {
        $this->guard->ensureEditable($product);
        abort_unless($image->product_id === $product->id, 404);

        if ($image->product_variant_id !== null) {
            throw ValidationException::withMessages(['image' => 'Only product gallery images can be primary product images.']);
        }

        return DB::transaction(function () use ($product, $image): ProductImage {
            $product->galleryImages()->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();
            $this->markChanged->handle($product);

            return $image;
        });
    }
}
