<?php

namespace Tests\Feature\Category;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Enums\VendorStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_edit_and_delete_categories(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/categories')->assertOk();
        $this->actingAs($admin)->get('/admin/categories/create')->assertOk();

        $this->actingAs($admin)
            ->post('/admin/categories', $this->categoryData(['name' => 'Electronics', 'slug' => '']))
            ->assertRedirect();

        $category = Category::query()->where('slug', 'electronics')->firstOrFail();
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)->get("/admin/categories/{$category->id}/edit")->assertOk();

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Consumer Electronics',
                'slug' => '',
                'sort_order' => 25,
                'is_active' => '0',
            ]))
            ->assertRedirect();

        $category->refresh();
        $this->assertSame('Consumer Electronics', $category->name);
        $this->assertSame('electronics', $category->slug);
        $this->assertFalse($category->is_active);
        $this->assertSame(25, $category->sort_order);

        $this->actingAs($admin)
            ->delete("/admin/categories/{$category->id}")
            ->assertRedirect('/admin/categories');

        $this->assertSoftDeleted($category);
    }

    public function test_vendor_customer_and_guest_cannot_manage_categories(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => VendorStatus::Approved]);

        $this->get('/admin/categories')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/categories')->assertForbidden();
        $this->actingAs($vendorUser)->get('/admin/categories')->assertForbidden();
    }

    public function test_child_category_creation_relationships_and_manual_slug(): void
    {
        $admin = $this->admin();
        $parent = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->actingAs($admin)
            ->post('/admin/categories', $this->categoryData([
                'parent_id' => $parent->id,
                'name' => 'Mobile Phones',
                'slug' => 'Mobiles & Phones',
                'sort_order' => 5,
            ]))
            ->assertRedirect();

        $category = Category::query()->where('slug', 'mobiles-phones')->firstOrFail();

        $this->assertTrue($category->parent->is($parent));
        $this->assertTrue($parent->children()->whereKey($category)->exists());
        $this->assertSame(5, $category->sort_order);
    }

    public function test_generated_slug_collision_gets_deterministic_suffix_and_manual_duplicate_fails(): void
    {
        $admin = $this->admin();
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->actingAs($admin)
            ->post('/admin/categories', $this->categoryData(['name' => 'Electronics', 'slug' => '']))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['slug' => 'electronics-2']);

        $this->actingAs($admin)
            ->from('/admin/categories/create')
            ->post('/admin/categories', $this->categoryData(['name' => 'Other', 'slug' => 'electronics']))
            ->assertRedirect('/admin/categories/create')
            ->assertSessionHasErrors('slug');
    }

    public function test_category_cannot_parent_itself_or_move_under_descendant(): void
    {
        $admin = $this->admin();
        $root = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $child = Category::factory()->childOf($root)->create(['name' => 'Mobile Phones', 'slug' => 'mobile-phones']);
        $grandchild = Category::factory()->childOf($child)->create(['name' => 'Smartphones', 'slug' => 'smartphones']);

        $this->actingAs($admin)
            ->from("/admin/categories/{$root->id}/edit")
            ->put("/admin/categories/{$root->id}", $this->categoryData([
                'name' => 'Electronics',
                'slug' => 'electronics',
                'parent_id' => $root->id,
            ]))
            ->assertRedirect("/admin/categories/{$root->id}/edit")
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($admin)
            ->from("/admin/categories/{$root->id}/edit")
            ->put("/admin/categories/{$root->id}", $this->categoryData([
                'name' => 'Electronics',
                'slug' => 'electronics',
                'parent_id' => $grandchild->id,
            ]))
            ->assertRedirect("/admin/categories/{$root->id}/edit")
            ->assertSessionHasErrors('parent_id');
    }

    public function test_category_can_change_parent_status_and_sort_order(): void
    {
        $admin = $this->admin();
        $oldParent = Category::factory()->create(['slug' => 'old-parent']);
        $newParent = Category::factory()->create(['slug' => 'new-parent']);
        $category = Category::factory()->childOf($oldParent)->create(['slug' => 'movable']);

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Moved Category',
                'slug' => 'movable-updated',
                'parent_id' => $newParent->id,
                'is_active' => '0',
                'sort_order' => 99,
            ]))
            ->assertRedirect();

        $category->refresh();
        $this->assertTrue($category->parent->is($newParent));
        $this->assertSame('movable-updated', $category->slug);
        $this->assertFalse($category->is_active);
        $this->assertSame(99, $category->sort_order);
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $parent = Category::factory()->create(['slug' => 'parent']);
        Category::factory()->childOf($parent)->create(['slug' => 'child']);

        $this->actingAs($admin)
            ->from('/admin/categories')
            ->delete("/admin/categories/{$parent->id}")
            ->assertRedirect('/admin/categories')
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $parent->id, 'deleted_at' => null]);
    }

    public function test_search_and_filters_work(): void
    {
        $admin = $this->admin();
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $fashion = Category::factory()->inactive()->create(['name' => 'Fashion', 'slug' => 'fashion']);
        Category::factory()->childOf($electronics)->create(['name' => 'Mobile Phones', 'slug' => 'mobile-phones']);

        $this->actingAs($admin)->get('/admin/categories?q=mobile')
            ->assertOk()
            ->assertSee('Mobile Phones')
            ->assertDontSee(route('admin.categories.edit', $fashion), false);
        $this->actingAs($admin)->get('/admin/categories?q=fashion')->assertOk()->assertSee('fashion');
        $this->actingAs($admin)->get('/admin/categories?status=active')
            ->assertOk()
            ->assertSee('Electronics')
            ->assertDontSee(route('admin.categories.edit', $fashion), false);
        $this->actingAs($admin)->get('/admin/categories?status=inactive')
            ->assertOk()
            ->assertSee('Fashion')
            ->assertDontSee(route('admin.categories.edit', $electronics), false);
        $this->actingAs($admin)->get("/admin/categories?parent_id={$electronics->id}")
            ->assertOk()
            ->assertSee('Mobile Phones')
            ->assertDontSee(route('admin.categories.edit', $fashion), false);
    }

    public function test_valid_image_and_icon_upload_and_replacement(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/categories', $this->categoryData([
                'image' => UploadedFile::fake()->image('category.jpg', 800, 500),
                'icon' => UploadedFile::fake()->image('icon.png', 100, 100),
            ]))
            ->assertRedirect();

        $category = Category::query()->firstOrFail();
        Storage::disk('public')->assertExists($category->image_path);
        Storage::disk('public')->assertExists($category->icon_path);

        $oldImage = $category->image_path;
        $oldIcon = $category->icon_path;

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'slug' => $category->slug,
                'image' => UploadedFile::fake()->image('new-category.jpg', 800, 500),
                'icon' => UploadedFile::fake()->image('new-icon.png', 100, 100),
            ]))
            ->assertRedirect();

        $category->refresh();
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertMissing($oldIcon);
        Storage::disk('public')->assertExists($category->image_path);
        Storage::disk('public')->assertExists($category->icon_path);
    }

    public function test_invalid_image_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/admin/categories/create')
            ->post('/admin/categories', $this->categoryData([
                'image' => UploadedFile::fake()->create('category.pdf', 10, 'application/pdf'),
            ]))
            ->assertRedirect('/admin/categories/create')
            ->assertSessionHasErrors('image');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function categoryData(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => null,
            'name' => 'Electronics',
            'slug' => 'electronics-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'A category for marketplace taxonomy.',
            'is_active' => '1',
            'sort_order' => 10,
            'seo_title' => 'Electronics',
            'seo_description' => 'Browse electronics in the marketplace.',
        ], $overrides);
    }
}
