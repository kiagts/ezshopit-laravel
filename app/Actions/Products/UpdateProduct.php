<?php

namespace App\Actions\Products;

use App\Actions\Concerns\GeneratesUniqueSlugs;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateProduct
{
    use GeneratesUniqueSlugs;

    public function __construct(
        private readonly SyncProductVariants $syncVariants,
        private readonly SyncProductSpecifications $syncSpecifications,
        private readonly MarkProductContentChanged $markChanged,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Vendor $vendor, Product $product, array $data): Product
    {
        if ($product->vendor_id !== $vendor->id) {
            throw ValidationException::withMessages(['product' => 'This product does not belong to the vendor.']);
        }

        if ($product->approval_status === ProductApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval_status' => 'Products under review cannot be edited.']);
        }

        return DB::transaction(function () use ($product, $data): Product {
            if (isset($data['type']) && ProductType::from((string) $data['type']) !== $product->type) {
                throw ValidationException::withMessages(['type' => 'Product type is immutable after creation.']);
            }

            if (isset($data['category_id']) && (int) $data['category_id'] !== $product->category_id && $this->hasStructuredData($product)) {
                throw ValidationException::withMessages(['category_id' => 'Product category cannot be changed after variants or specifications exist.']);
            }

            if (array_key_exists('brand_id', $data) && filled($data['brand_id'])) {
                $brand = Brand::query()->whereKey((int) $data['brand_id'])->where('is_active', true)->first();
                if (! $brand) {
                    throw ValidationException::withMessages(['brand_id' => 'The selected brand is not available.']);
                }
            }

            if (array_key_exists('category_id', $data) && ! $this->hasStructuredData($product)) {
                $category = Category::query()->findOrFail((int) $data['category_id']);
                if (! $category->isPubliclyUsable()) {
                    throw ValidationException::withMessages(['category_id' => 'Products must use an active leaf category with active ancestors.']);
                }
            }

            if (array_key_exists('slug', $data)) {
                $data['slug'] = filled($data['slug'])
                    ? Str::slug((string) $data['slug'])
                    : $this->uniqueSlugFor(Product::class, (string) ($data['name'] ?? $product->name), $product);

                if (Product::query()->whereKeyNot($product->id)->where('slug', $data['slug'])->exists()) {
                    throw ValidationException::withMessages(['slug' => 'Product slug must be unique.']);
                }
            }

            if (array_key_exists('brand_id', $data) && blank($data['brand_id'])) {
                $data['brand_id'] = null;
            }

            $product->fill(collect($data)->only([
                'category_id',
                'brand_id',
                'name',
                'slug',
                'status',
                'short_description',
                'description',
            ])->all());

            $product->save();

            if ($product->approval_status === ProductApprovalStatus::Approved) {
                $this->markChanged->handle($product);
            }

            $category = $product->category()->with(['productAttributeSet.attributes'])->firstOrFail();

            if (array_key_exists('variants', $data)) {
                $this->syncVariants->handle($product, $category, $data['variants'] ?? []);
            }

            if (array_key_exists('specifications', $data)) {
                $this->syncSpecifications->handle($product, $category, $data['specifications'] ?? []);
            }

            return $product->refresh()->load(['variants.attributeValues', 'specifications.values']);
        });
    }

    private function hasStructuredData(Product $product): bool
    {
        return $product->variants()->exists() || $product->specifications()->exists();
    }
}
