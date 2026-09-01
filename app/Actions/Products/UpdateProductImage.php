<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;

class UpdateProductImage
{
    public function __construct(
        private readonly AddProductImages $guard,
        private readonly MarkProductContentChanged $markChanged,
    ) {}

    public function handle(Product $product, ProductImage $image, ?string $altText): ProductImage
    {
        $this->guard->ensureEditable($product);
        $this->ensureImageBelongsToProduct($product, $image);

        $image->update(['alt_text' => $altText]);
        $this->markChanged->handle($product);

        return $image;
    }

    private function ensureImageBelongsToProduct(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->id, 404);
    }
}
