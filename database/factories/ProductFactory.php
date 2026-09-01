<?php

namespace Database\Factories;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'vendor_id' => Vendor::factory()->approved(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'approval_status' => ProductApprovalStatus::Draft,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'is_featured' => false,
            'rejection_reason' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'published_at' => null,
        ];
    }

    public function simple(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Simple,
        ]);
    }

    public function variant(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Variant,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Active,
            'approval_status' => ProductApprovalStatus::Approved,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);
    }
}
