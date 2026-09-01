<?php

namespace Tests\Feature\Brand;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_edit_and_delete_brands(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/brands')->assertOk();
        $this->actingAs($admin)->get('/admin/brands/create')->assertOk();

        $this->actingAs($admin)
            ->post('/admin/brands', $this->brandData([
                'name' => 'Apple',
                'slug' => '',
                'description' => 'Apple is a technology brand.',
                'seo_title' => 'Apple Brand',
                'seo_description' => 'Browse Apple products.',
            ]))
            ->assertRedirect();

        $brand = Brand::query()->where('slug', 'apple')->firstOrFail();
        $this->assertTrue($brand->is_active);
        $this->assertSame('Apple is a technology brand.', $brand->description);
        $this->assertSame('Apple Brand', $brand->seo_title);

        $this->actingAs($admin)->get("/admin/brands/{$brand->id}/edit")->assertOk();

        $this->actingAs($admin)
            ->put("/admin/brands/{$brand->id}", $this->brandData([
                'name' => 'Apple Inc',
                'slug' => '',
                'description' => 'Updated description.',
                'is_active' => '0',
                'sort_order' => 40,
                'seo_title' => 'Updated SEO',
                'seo_description' => 'Updated SEO description.',
            ]))
            ->assertRedirect();

        $brand->refresh();
        $this->assertSame('Apple Inc', $brand->name);
        $this->assertSame('apple', $brand->slug);
        $this->assertFalse($brand->is_active);
        $this->assertSame(40, $brand->sort_order);
        $this->assertSame('Updated description.', $brand->description);
        $this->assertSame('Updated SEO', $brand->seo_title);

        $this->actingAs($admin)
            ->delete("/admin/brands/{$brand->id}")
            ->assertRedirect('/admin/brands');

        $this->assertSoftDeleted($brand);
    }

    public function test_vendor_customer_and_guest_cannot_manage_brands(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => VendorStatus::Approved]);

        $this->get('/admin/brands')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/brands')->assertForbidden();
        $this->actingAs($vendorUser)->get('/admin/brands')->assertForbidden();
    }

    public function test_manual_slug_duplicate_slug_and_generated_collision_behavior(): void
    {
        $admin = $this->admin();
        Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple']);

        $this->actingAs($admin)
            ->post('/admin/brands', $this->brandData(['name' => 'Apple', 'slug' => '']))
            ->assertRedirect();

        $this->assertDatabaseHas('brands', ['slug' => 'apple-2']);

        $this->actingAs($admin)
            ->post('/admin/brands', $this->brandData(['name' => 'Samsung', 'slug' => 'Samsung Electronics']))
            ->assertRedirect();

        $this->assertDatabaseHas('brands', ['slug' => 'samsung-electronics']);

        $this->actingAs($admin)
            ->from('/admin/brands/create')
            ->post('/admin/brands', $this->brandData(['name' => 'Other', 'slug' => 'apple']))
            ->assertRedirect('/admin/brands/create')
            ->assertSessionHasErrors('slug');
    }

    public function test_brand_can_explicitly_change_slug_status_sort_description_and_seo(): void
    {
        $admin = $this->admin();
        $brand = Brand::factory()->create(['name' => 'Old Brand', 'slug' => 'old-brand']);

        $this->actingAs($admin)
            ->put("/admin/brands/{$brand->id}", $this->brandData([
                'name' => 'New Brand',
                'slug' => 'new-brand',
                'description' => 'New description.',
                'is_active' => '0',
                'sort_order' => 77,
                'seo_title' => 'New SEO',
                'seo_description' => 'New SEO description.',
            ]))
            ->assertRedirect();

        $brand->refresh();
        $this->assertSame('new-brand', $brand->slug);
        $this->assertFalse($brand->is_active);
        $this->assertSame(77, $brand->sort_order);
        $this->assertSame('New description.', $brand->description);
        $this->assertSame('New SEO description.', $brand->seo_description);
    }

    public function test_duplicate_manual_slug_is_rejected_on_update(): void
    {
        $admin = $this->admin();
        Brand::factory()->create(['slug' => 'apple']);
        $brand = Brand::factory()->create(['slug' => 'samsung']);

        $this->actingAs($admin)
            ->from("/admin/brands/{$brand->id}/edit")
            ->put("/admin/brands/{$brand->id}", $this->brandData([
                'name' => 'Samsung',
                'slug' => 'apple',
            ]))
            ->assertRedirect("/admin/brands/{$brand->id}/edit")
            ->assertSessionHasErrors('slug');
    }

    public function test_valid_logo_upload_replacement_and_preservation(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/brands', $this->brandData([
                'logo' => UploadedFile::fake()->image('logo.png', 240, 240),
            ]))
            ->assertRedirect();

        $brand = Brand::query()->firstOrFail();
        Storage::disk('public')->assertExists($brand->logo_path);
        $oldLogo = $brand->logo_path;

        $this->actingAs($admin)
            ->put("/admin/brands/{$brand->id}", $this->brandData([
                'slug' => $brand->slug,
                'name' => 'Preserved Logo Brand',
            ]))
            ->assertRedirect();

        $brand->refresh();
        $this->assertSame($oldLogo, $brand->logo_path);
        Storage::disk('public')->assertExists($oldLogo);

        $this->actingAs($admin)
            ->put("/admin/brands/{$brand->id}", $this->brandData([
                'slug' => $brand->slug,
                'logo' => UploadedFile::fake()->image('new-logo.jpg', 240, 240),
            ]))
            ->assertRedirect();

        $brand->refresh();
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertExists($brand->logo_path);
    }

    public function test_invalid_logo_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/admin/brands/create')
            ->post('/admin/brands', $this->brandData([
                'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            ]))
            ->assertRedirect('/admin/brands/create')
            ->assertSessionHasErrors('logo');
    }

    public function test_search_and_status_filters_work(): void
    {
        $admin = $this->admin();
        $apple = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple', 'is_active' => true]);
        $sony = Brand::factory()->inactive()->create(['name' => 'Sony', 'slug' => 'sony-audio']);

        $this->actingAs($admin)->get('/admin/brands?q=apple')
            ->assertOk()
            ->assertSee('Apple')
            ->assertDontSee(route('admin.brands.edit', $sony), false);

        $this->actingAs($admin)->get('/admin/brands?q=audio')
            ->assertOk()
            ->assertSee('sony-audio')
            ->assertDontSee(route('admin.brands.edit', $apple), false);

        $this->actingAs($admin)->get('/admin/brands?status=active')
            ->assertOk()
            ->assertSee('Apple')
            ->assertDontSee(route('admin.brands.edit', $sony), false);

        $this->actingAs($admin)->get('/admin/brands?status=inactive')
            ->assertOk()
            ->assertSee('Sony')
            ->assertDontSee(route('admin.brands.edit', $apple), false);
    }

    public function test_deleted_brand_slug_remains_reserved(): void
    {
        $admin = $this->admin();
        $brand = Brand::factory()->create(['slug' => 'reserved-brand']);

        $this->actingAs($admin)->delete("/admin/brands/{$brand->id}")->assertRedirect('/admin/brands');

        $this->actingAs($admin)
            ->from('/admin/brands/create')
            ->post('/admin/brands', $this->brandData(['name' => 'Reserved Brand', 'slug' => 'reserved-brand']))
            ->assertRedirect('/admin/brands/create')
            ->assertSessionHasErrors('slug');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function brandData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Apple',
            'slug' => 'apple-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'A brand prepared for marketplace product assignment.',
            'is_active' => '1',
            'sort_order' => 10,
            'seo_title' => 'Apple',
            'seo_description' => 'Browse Apple in the marketplace.',
        ], $overrides);
    }
}
