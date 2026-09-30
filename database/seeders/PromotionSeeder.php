<?php

namespace Database\Seeders;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFundingSource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $vendor = Vendor::query()->where('slug', 'techverse')->first();
        $featuredProduct = Product::query()->where('vendor_id', $vendor?->id)->orderBy('id')->first();
        $category = Category::query()->active()->orderBy('id')->first();

        $marketplace = Promotion::query()->updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'name' => 'Welcome 10 Percent Off',
                'funding_source' => PromotionFundingSource::Marketplace,
                'vendor_id' => null,
                'discount_type' => PromotionDiscountType::Percentage,
                'discount_value' => 1000,
                'minimum_spend' => 5000,
                'maximum_discount' => 2500,
                'usage_limit' => 500,
                'per_customer_limit' => 1,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'is_active' => true,
                'created_by' => $admin?->id,
            ],
        );

        if ($category) {
            $marketplace->categories()->sync([$category->id]);
        }

        if ($vendor) {
            $vendorPromotion = Promotion::query()->updateOrCreate(
                ['code' => 'TECH500'],
                [
                    'name' => 'TechVerse Fixed Savings',
                    'funding_source' => PromotionFundingSource::Vendor,
                    'vendor_id' => $vendor->id,
                    'discount_type' => PromotionDiscountType::FixedAmount,
                    'discount_value' => 500,
                    'minimum_spend' => 3000,
                    'maximum_discount' => null,
                    'usage_limit' => 100,
                    'per_customer_limit' => 2,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                    'is_active' => true,
                    'created_by' => $admin?->id,
                ],
            );

            $vendorPromotion->products()->sync($featuredProduct ? [$featuredProduct->id] : []);
        }
    }
}
