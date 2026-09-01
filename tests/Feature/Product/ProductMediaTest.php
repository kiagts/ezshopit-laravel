<?php

namespace Tests\Feature\Product;

use App\Actions\Products\CreateProduct;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_vendor_can_upload_gallery_images_and_first_general_image_becomes_primary(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images", [
            'images' => [
                UploadedFile::fake()->image('front.jpg', 800, 800),
                UploadedFile::fake()->image('back.png', 800, 800),
            ],
            'alt_text' => 'Phone front',
        ])->assertRedirect("/vendor/products/{$product->id}/edit");

        $images = $product->images()->get();
        $this->assertSame(2, $images->count());
        $this->assertTrue($images->first()->is_primary);
        $this->assertFalse($images->last()->is_primary);
        $this->assertSame([0, 1], $images->pluck('sort_order')->all());
        Storage::disk('public')->assertExists($images->first()->path);
        $this->assertStringStartsWith("products/{$product->id}/images/", $images->first()->path);
        $this->assertStringNotContainsString('front', $images->first()->path);
    }

    public function test_invalid_uploads_are_rejected_without_storing_records(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();

        foreach ([
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->image('huge.jpg')->size(6000),
        ] as $file) {
            $this->actingAs($vendor->user)
                ->from("/vendor/products/{$product->id}/edit")
                ->post("/vendor/products/{$product->id}/images", ['images' => [$file]])
                ->assertRedirect("/vendor/products/{$product->id}/edit")
                ->assertSessionHasErrors('images.0');
        }

        $this->assertSame(0, $product->images()->count());
        Storage::disk('public')->assertMissing("products/{$product->id}/images/notes.txt");
    }

    public function test_setting_primary_unsets_previous_primary(): void
    {
        [$vendor, $product] = $this->product();
        $first = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images/{$second->id}/primary")->assertRedirect();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $product->galleryImages()->where('is_primary', true)->count());
    }

    public function test_variant_image_assignment_requires_variant_to_belong_to_product(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();
        $variant = $product->defaultVariant;
        [, $otherProduct] = $this->product();
        $otherVariant = $otherProduct->defaultVariant;

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images", [
            'product_variant_id' => $variant->id,
            'images' => [UploadedFile::fake()->image('variant.webp', 800, 800)],
        ])->assertRedirect();

        $image = $product->images()->variantSpecific()->firstOrFail();
        $this->assertSame($variant->id, $image->product_variant_id);
        $this->assertFalse($image->is_primary);
        Storage::disk('public')->assertExists($image->path);

        $this->actingAs($vendor->user)
            ->from("/vendor/products/{$product->id}/edit")
            ->post("/vendor/products/{$product->id}/images", [
                'product_variant_id' => $otherVariant->id,
                'images' => [UploadedFile::fake()->image('wrong.jpg', 800, 800)],
            ])
            ->assertRedirect("/vendor/products/{$product->id}/edit")
            ->assertSessionHasErrors('product_variant_id');
    }

    public function test_gallery_and_variant_image_limits_are_enforced(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();
        ProductImage::factory()->count(8)->sequence(fn ($sequence) => ['product_id' => $product->id, 'sort_order' => $sequence->index])->create();

        $this->actingAs($vendor->user)
            ->from("/vendor/products/{$product->id}/edit")
            ->post("/vendor/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('extra.jpg')]])
            ->assertRedirect("/vendor/products/{$product->id}/edit")
            ->assertSessionHasErrors('images');

        [, $variantProduct] = $this->product('variant-limit-product');
        $variant = $variantProduct->defaultVariant;
        ProductImage::factory()->count(3)->sequence(fn ($sequence) => [
            'product_id' => $variantProduct->id,
            'product_variant_id' => $variant->id,
            'sort_order' => $sequence->index,
        ])->create();

        $this->actingAs($variantProduct->vendor->user)
            ->from("/vendor/products/{$variantProduct->id}/edit")
            ->post("/vendor/products/{$variantProduct->id}/images", [
                'product_variant_id' => $variant->id,
                'images' => [UploadedFile::fake()->image('variant-extra.jpg')],
            ])
            ->assertRedirect("/vendor/products/{$variantProduct->id}/edit")
            ->assertSessionHasErrors('images');
    }

    public function test_delete_removes_file_soft_deletes_record_and_promotes_next_primary(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();
        Storage::disk('public')->put('products/1/images/a.jpg', 'a');
        Storage::disk('public')->put('products/1/images/b.jpg', 'b');
        $first = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'path' => 'products/1/images/a.jpg', 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'products/1/images/b.jpg', 'sort_order' => 1]);

        $this->actingAs($vendor->user)->delete("/vendor/products/{$product->id}/images/{$first->id}")->assertRedirect();

        $this->assertSoftDeleted($first);
        Storage::disk('public')->assertMissing('products/1/images/a.jpg');
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(0, $second->fresh()->sort_order);
    }

    public function test_vendor_cannot_manage_another_product_image_or_cross_product_reorder(): void
    {
        [$owner, $product] = $this->product();
        [$otherVendor, $otherProduct] = $this->product('other-image-product');
        $image = ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        $otherImage = ProductImage::factory()->primary()->create(['product_id' => $otherProduct->id]);

        $this->actingAs($otherVendor->user)->delete("/vendor/products/{$product->id}/images/{$image->id}")->assertForbidden();
        $this->actingAs($owner->user)
            ->from("/vendor/products/{$product->id}/edit")
            ->post("/vendor/products/{$product->id}/images/reorder", ['images' => [$image->id, $otherImage->id]])
            ->assertRedirect("/vendor/products/{$product->id}/edit")
            ->assertSessionHasErrors('images');
    }

    public function test_reorder_and_alt_text_update_work(): void
    {
        [$vendor, $product] = $this->product();
        $a = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $b = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);
        $variantImage = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->defaultVariant->id,
            'sort_order' => 0,
        ]);

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}/images/{$a->id}", ['alt_text' => 'Front angle'])->assertRedirect();
        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images/reorder", ['images' => [$b->id, $a->id]])->assertRedirect();
        $this->actingAs($vendor->user)
            ->from("/vendor/products/{$product->id}/edit")
            ->post("/vendor/products/{$product->id}/images/reorder", ['images' => [$variantImage->id]])
            ->assertRedirect("/vendor/products/{$product->id}/edit")
            ->assertSessionHasErrors('images');

        $this->assertSame('Front angle', $a->fresh()->alt_text);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertTrue($a->fresh()->is_primary);
    }

    public function test_pending_and_archived_products_lock_media_changes(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();
        $image = ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        $product->forceFill(['approval_status' => ProductApprovalStatus::Pending, 'status' => ProductStatus::Active])->save();

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('x.jpg')]])->assertRedirect()->assertSessionHasErrors('approval_status');
        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images/{$image->id}/primary")->assertRedirect()->assertSessionHasErrors('approval_status');
        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images/reorder", ['images' => [$image->id]])->assertRedirect()->assertSessionHasErrors('approval_status');
        $this->actingAs($vendor->user)->delete("/vendor/products/{$product->id}/images/{$image->id}")->assertRedirect()->assertSessionHasErrors('approval_status');

        $product->forceFill(['approval_status' => ProductApprovalStatus::Draft, 'status' => ProductStatus::Archived])->save();
        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('y.jpg')]])->assertRedirect()->assertSessionHasErrors('status');
    }

    public function test_approved_product_media_changes_require_reapproval_and_rejected_stays_rejected(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        $product->forceFill([
            'status' => ProductStatus::Active,
            'approval_status' => ProductApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame(ProductApprovalStatus::Draft, $product->approval_status);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertNull($product->approved_at);
        $this->assertNull($product->approved_by);

        [, $rejected] = $this->product('rejected-media-product');
        $rejected->forceFill(['approval_status' => ProductApprovalStatus::Rejected, 'status' => ProductStatus::Draft])->save();
        $this->actingAs($rejected->vendor->user)->post("/vendor/products/{$rejected->id}/images", [
            'images' => [UploadedFile::fake()->image('fix.jpg')],
        ])->assertRedirect();
        $this->assertSame(ProductApprovalStatus::Rejected, $rejected->fresh()->approval_status);
    }

    public function test_submission_validator_requires_primary_general_image(): void
    {
        [$vendor, $product] = $this->product();
        $validator = app(\App\Services\ProductSubmissionValidator::class);

        $this->assertFalse($validator->ready($product));
        $this->assertContains('Choose a primary product image before submitting for review.', $validator->errors($product));

        ProductImage::factory()->create(['product_id' => $product->id, 'is_primary' => false]);
        $this->assertFalse($validator->ready($product->fresh()));

        ProductImage::factory()->primary()->create(['product_id' => $product->id, 'sort_order' => 1]);
        $this->assertTrue($validator->ready($product->fresh()));
    }

    public function test_roles_and_guests_cannot_use_vendor_media_routes(): void
    {
        [$vendor, $product] = $this->product();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($customer)->post("/vendor/products/{$product->id}/images")->assertForbidden();
        $this->actingAs($admin)->post("/vendor/products/{$product->id}/images")->assertForbidden();
        auth()->logout();
        $this->post("/vendor/products/{$product->id}/images")->assertRedirect('/login');

        $pending = $this->vendor(VendorStatus::Pending);
        $this->actingAs($pending->user)->post("/vendor/products/{$product->id}/images")->assertRedirect('/vendor/application-status');
    }

    private function product(string $slug = 'media-product'): array
    {
        $vendor = $this->vendor(VendorStatus::Approved);
        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);
        $product = app(CreateProduct::class)->handle($vendor, [
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug.'-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductType::Simple->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'short_description' => 'Media product summary.',
            'description' => 'A complete media product description.',
            'variants' => [['sku' => 'MEDIA-'.fake()->unique()->numberBetween(1000, 9999), 'base_price' => 1000]],
            'specifications' => [],
        ]);

        return [$vendor, $product];
    }

    private function vendor(VendorStatus $status): Vendor
    {
        $user = User::factory()->create([
            'role' => UserRole::Vendor,
            'password' => Hash::make('password'),
        ]);

        return Vendor::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }
}
