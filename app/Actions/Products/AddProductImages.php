<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AddProductImages
{
    public function __construct(private readonly MarkProductContentChanged $markChanged) {}

    /**
     * @param array<int, UploadedFile> $files
     */
    public function handle(Product $product, array $files, ?ProductVariant $variant, ?string $altText = null): array
    {
        $this->ensureEditable($product);
        $this->ensureVariantBelongsToProduct($product, $variant);
        $this->ensureCapacity($product, $variant, count($files));

        $disk = $this->disk();
        $storedPaths = [];

        try {
            foreach ($files as $file) {
                $storedPaths[] = $file->store("products/{$product->id}/images", $disk);
            }

            return DB::transaction(function () use ($product, $variant, $altText, $storedPaths): array {
                $nextSortOrder = $this->nextSortOrder($product, $variant);
                $makePrimary = $variant === null && ! $product->galleryImages()->where('is_primary', true)->exists();
                $images = [];

                foreach ($storedPaths as $index => $path) {
                    $images[] = $product->images()->create([
                        'product_variant_id' => $variant?->id,
                        'path' => $path,
                        'alt_text' => $altText,
                        'is_primary' => $variant === null && $makePrimary && $index === 0,
                        'sort_order' => $nextSortOrder + $index,
                    ]);
                }

                $this->markChanged->handle($product);

                return $images;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    public function ensureEditable(Product $product): void
    {
        if ($product->approval_status === ProductApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval_status' => 'Products under review cannot have media changes.']);
        }

        if ($product->status === ProductStatus::Archived) {
            throw ValidationException::withMessages(['status' => 'Archived products cannot have media changes.']);
        }
    }

    public function ensureVariantBelongsToProduct(Product $product, ?ProductVariant $variant): void
    {
        if ($variant && $variant->product_id !== $product->id) {
            throw ValidationException::withMessages(['product_variant_id' => 'The selected variant does not belong to this product.']);
        }
    }

    private function ensureCapacity(Product $product, ?ProductVariant $variant, int $incomingCount): void
    {
        $currentCount = $variant
            ? $product->images()->where('product_variant_id', $variant->id)->count()
            : $product->galleryImages()->count();
        $maximum = $variant
            ? (int) config('marketplace.product_media.max_variant_images', 3)
            : (int) config('marketplace.product_media.max_gallery_images', 8);

        if ($currentCount + $incomingCount > $maximum) {
            $label = $variant ? 'variant images' : 'product gallery images';
            throw ValidationException::withMessages(['images' => "This product can have at most {$maximum} {$label}."]);
        }
    }

    private function nextSortOrder(Product $product, ?ProductVariant $variant): int
    {
        $max = $product->images()
            ->when($variant, fn ($query) => $query->where('product_variant_id', $variant->id), fn ($query) => $query->whereNull('product_variant_id'))
            ->max('sort_order');

        return $max === null ? 0 : ((int) $max + 1);
    }

    private function disk(): string
    {
        return (string) config('marketplace.product_media.disk', 'public');
    }
}
