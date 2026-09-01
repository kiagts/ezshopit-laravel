<?php

namespace Tests\Feature\ProductAttributeSet;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeSet;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductAttributeSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAttributeSetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_create_edit_and_delete_empty_attribute_set(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/attribute-sets')->assertOk();
        $this->actingAs($admin)->get('/admin/attribute-sets/create')->assertOk();

        $this->actingAs($admin)
            ->post('/admin/attribute-sets', $this->attributeSetData([
                'name' => 'Smartphone',
                'code' => '',
                'description' => 'Phone catalog configuration.',
                'is_active' => '0',
                'sort_order' => 20,
                'attributes' => [],
            ]))
            ->assertRedirect();

        $set = ProductAttributeSet::query()->where('code', 'smartphone')->firstOrFail();
        $this->assertSame('Phone catalog configuration.', $set->description);
        $this->assertFalse($set->is_active);
        $this->assertSame(20, $set->sort_order);

        $this->actingAs($admin)->get("/admin/attribute-sets/{$set->id}/edit")->assertOk();

        $this->actingAs($admin)
            ->delete("/admin/attribute-sets/{$set->id}")
            ->assertRedirect('/admin/attribute-sets');

        $this->assertSoftDeleted($set);
    }

    public function test_vendor_customer_and_guest_cannot_manage_attribute_sets(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => VendorStatus::Approved]);

        $this->get('/admin/attribute-sets')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/attribute-sets')->assertForbidden();
        $this->actingAs($vendorUser)->get('/admin/attribute-sets')->assertForbidden();
    }

    public function test_set_creation_with_attributes_contextual_required_and_sort_order(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color']);
        $processor = ProductAttribute::factory()->create([
            'name' => 'Processor',
            'code' => 'processor',
            'type' => ProductAttributeType::Specification,
            'input_type' => ProductAttributeInputType::Text,
            'sort_order' => 5,
        ]);

        $this->actingAs($admin)
            ->post('/admin/attribute-sets', $this->attributeSetData([
                'name' => 'Smartphone',
                'code' => 'Smart Phone',
                'attributes' => [
                    ['product_attribute_id' => $processor->id, 'is_required' => '1', 'sort_order' => 20],
                    ['product_attribute_id' => $color->id, 'is_required' => '1', 'sort_order' => 10],
                ],
            ]))
            ->assertRedirect();

        $set = ProductAttributeSet::query()->where('code', 'smart_phone')->firstOrFail();

        $this->assertDatabaseHas('product_attribute_set_attributes', [
            'product_attribute_set_id' => $set->id,
            'product_attribute_id' => $color->id,
            'is_required' => true,
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('product_attribute_set_attributes', [
            'product_attribute_set_id' => $set->id,
            'product_attribute_id' => $processor->id,
            'is_required' => true,
            'sort_order' => 20,
        ]);
    }

    public function test_duplicate_attribute_set_code_is_rejected(): void
    {
        $admin = $this->admin();
        ProductAttributeSet::factory()->create(['code' => 'smartphone']);

        $this->actingAs($admin)
            ->from('/admin/attribute-sets/create')
            ->post('/admin/attribute-sets', $this->attributeSetData([
                'name' => 'Other Smartphone',
                'code' => 'smartphone',
            ]))
            ->assertRedirect('/admin/attribute-sets/create')
            ->assertSessionHasErrors('code');
    }


    public function test_duplicate_and_invalid_attribute_assignments_are_rejected(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['code' => 'color']);
        $inactive = ProductAttribute::factory()->inactive()->create(['code' => 'inactive_attribute']);
        $deleted = ProductAttribute::factory()->create(['code' => 'deleted_attribute']);
        $deleted->delete();

        $this->actingAs($admin)
            ->from('/admin/attribute-sets/create')
            ->post('/admin/attribute-sets', $this->attributeSetData([
                'attributes' => [
                    ['product_attribute_id' => $color->id, 'sort_order' => 10],
                    ['product_attribute_id' => $color->id, 'sort_order' => 20],
                ],
            ]))
            ->assertRedirect('/admin/attribute-sets/create')
            ->assertSessionHasErrors(['attributes', 'attributes.1.product_attribute_id']);

        $this->actingAs($admin)
            ->from('/admin/attribute-sets/create')
            ->post('/admin/attribute-sets', $this->attributeSetData([
                'code' => 'inactive-test',
                'attributes' => [
                    ['product_attribute_id' => $inactive->id, 'sort_order' => 10],
                ],
            ]))
            ->assertRedirect('/admin/attribute-sets/create')
            ->assertSessionHasErrors('attributes.0.product_attribute_id');

        $this->actingAs($admin)
            ->from('/admin/attribute-sets/create')
            ->post('/admin/attribute-sets', $this->attributeSetData([
                'code' => 'deleted-test',
                'attributes' => [
                    ['product_attribute_id' => $deleted->id, 'sort_order' => 10],
                ],
            ]))
            ->assertRedirect('/admin/attribute-sets/create')
            ->assertSessionHasErrors('attributes.0.product_attribute_id');
    }

    public function test_update_adds_removes_and_reorders_attribute_assignments_without_changing_name_generated_code(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['code' => 'color']);
        $storage = ProductAttribute::factory()->variant()->create(['code' => 'storage']);
        $processor = ProductAttribute::factory()->create(['code' => 'processor']);
        $set = ProductAttributeSet::factory()->create(['name' => 'Smartphone', 'code' => 'smartphone']);
        $set->attributes()->attach($color->id, ['is_required' => true, 'sort_order' => 10]);

        $this->actingAs($admin)
            ->put("/admin/attribute-sets/{$set->id}", $this->attributeSetData([
                'name' => 'Smartphone Catalog',
                'code' => '',
                'attributes' => [
                    ['product_attribute_id' => $storage->id, 'is_required' => '1', 'sort_order' => 20],
                    ['product_attribute_id' => $processor->id, 'is_required' => '0', 'sort_order' => 30],
                ],
            ]))
            ->assertRedirect();

        $set->refresh();
        $this->assertSame('Smartphone Catalog', $set->name);
        $this->assertSame('smartphone', $set->code);
        $this->assertFalse($set->attributes()->whereKey($color)->exists());
        $this->assertTrue($set->attributes()->whereKey($storage)->exists());
        $this->assertFalse((bool) $set->attributes()->whereKey($processor)->firstOrFail()->pivot->is_required);

        $this->actingAs($admin)
            ->put("/admin/attribute-sets/{$set->id}", $this->attributeSetData([
                'name' => 'Smartphone Catalog',
                'code' => 'smartphone_v2',
                'attributes' => [],
            ]))
            ->assertRedirect();

        $this->assertSame('smartphone_v2', $set->refresh()->code);
    }

    public function test_product_attribute_used_by_set_cannot_be_deleted_and_value_rule_still_applies(): void
    {
        $admin = $this->admin();
        $usedAttribute = ProductAttribute::factory()->create(['code' => 'used_attribute']);
        $set = ProductAttributeSet::factory()->create();
        $set->attributes()->attach($usedAttribute->id, ['sort_order' => 10]);

        $this->actingAs($admin)
            ->from("/admin/attributes/{$usedAttribute->id}/edit")
            ->delete("/admin/attributes/{$usedAttribute->id}")
            ->assertRedirect("/admin/attributes/{$usedAttribute->id}/edit")
            ->assertSessionHasErrors('attribute');

        $attributeWithValues = ProductAttribute::factory()->variant()->create(['code' => 'has_values']);
        ProductAttributeValue::factory()->create(['product_attribute_id' => $attributeWithValues->id]);

        $this->actingAs($admin)
            ->from("/admin/attributes/{$attributeWithValues->id}/edit")
            ->delete("/admin/attributes/{$attributeWithValues->id}")
            ->assertRedirect("/admin/attributes/{$attributeWithValues->id}/edit")
            ->assertSessionHasErrors('attribute');
    }

    public function test_attribute_set_assigned_to_category_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $set = ProductAttributeSet::factory()->create();
        Category::factory()->create(['product_attribute_set_id' => $set->id]);

        $this->actingAs($admin)
            ->from('/admin/attribute-sets')
            ->delete("/admin/attribute-sets/{$set->id}")
            ->assertRedirect('/admin/attribute-sets')
            ->assertSessionHasErrors('attribute_set');

        $this->assertDatabaseHas('product_attribute_sets', ['id' => $set->id, 'deleted_at' => null]);
    }

    public function test_admin_can_assign_change_and_remove_category_attribute_set(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'smartphones']);
        $smartphone = ProductAttributeSet::factory()->create(['code' => 'smartphone']);
        $laptop = ProductAttributeSet::factory()->create(['code' => 'laptop']);

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'product_attribute_set_id' => $smartphone->id,
            ]))
            ->assertRedirect();

        $this->assertTrue($category->refresh()->productAttributeSet->is($smartphone));

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'product_attribute_set_id' => $laptop->id,
            ]))
            ->assertRedirect();

        $this->assertTrue($category->refresh()->productAttributeSet->is($laptop));

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'product_attribute_set_id' => null,
            ]))
            ->assertRedirect();

        $this->assertNull($category->refresh()->product_attribute_set_id);
    }

    public function test_invalid_deleted_and_inactive_category_set_assignments_are_rejected_but_existing_inactive_is_preserved(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'smartphones']);
        $inactive = ProductAttributeSet::factory()->inactive()->create(['code' => 'inactive_set']);
        $deleted = ProductAttributeSet::factory()->create(['code' => 'deleted_set']);
        $deleted->delete();

        $this->actingAs($admin)
            ->from("/admin/categories/{$category->id}/edit")
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'product_attribute_set_id' => $inactive->id,
            ]))
            ->assertRedirect("/admin/categories/{$category->id}/edit")
            ->assertSessionHasErrors('product_attribute_set_id');

        $this->actingAs($admin)
            ->from("/admin/categories/{$category->id}/edit")
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'product_attribute_set_id' => $deleted->id,
            ]))
            ->assertRedirect("/admin/categories/{$category->id}/edit")
            ->assertSessionHasErrors('product_attribute_set_id');

        $category->forceFill(['product_attribute_set_id' => $inactive->id])->save();

        $this->actingAs($admin)
            ->put("/admin/categories/{$category->id}", $this->categoryData([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'product_attribute_set_id' => $inactive->id,
            ]))
            ->assertRedirect();

        $this->assertSame($inactive->id, $category->refresh()->product_attribute_set_id);
    }

    public function test_relationships_and_ordering_use_pivot_sort_order(): void
    {
        $category = Category::factory()->create();
        $set = ProductAttributeSet::factory()->create();
        $slowGlobal = ProductAttribute::factory()->variant()->create(['name' => 'Storage', 'code' => 'storage', 'sort_order' => 1]);
        $fastGlobal = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color', 'sort_order' => 99]);

        $category->update(['product_attribute_set_id' => $set->id]);
        $set->attributes()->attach($slowGlobal->id, ['is_required' => false, 'sort_order' => 20]);
        $set->attributes()->attach($fastGlobal->id, ['is_required' => true, 'sort_order' => 10]);

        $this->assertTrue($category->productAttributeSet->is($set));
        $this->assertTrue($set->categories()->whereKey($category)->exists());
        $this->assertTrue($slowGlobal->attributeSets()->whereKey($set)->exists());
        $this->assertSame(['color', 'storage'], $set->attributes()->pluck('product_attributes.code')->all());
        $this->assertTrue((bool) $set->attributes()->whereKey($fastGlobal)->firstOrFail()->pivot->is_required);
    }

    public function test_search_status_filters_and_pages_render(): void
    {
        $admin = $this->admin();
        $smartphone = ProductAttributeSet::factory()->create(['name' => 'Smartphone', 'code' => 'smartphone', 'is_active' => true]);
        $laptop = ProductAttributeSet::factory()->inactive()->create(['name' => 'Laptop', 'code' => 'laptop']);

        $this->actingAs($admin)->get('/admin/attribute-sets?q=smart')
            ->assertOk()
            ->assertSee('Smartphone')
            ->assertDontSee(route('admin.attribute-sets.edit', $laptop), false);

        $this->actingAs($admin)->get('/admin/attribute-sets?q=laptop')
            ->assertOk()
            ->assertSee('Laptop')
            ->assertDontSee(route('admin.attribute-sets.edit', $smartphone), false);

        $this->actingAs($admin)->get('/admin/attribute-sets?status=active')
            ->assertOk()
            ->assertSee('Smartphone')
            ->assertDontSee(route('admin.attribute-sets.edit', $laptop), false);

        $this->actingAs($admin)->get('/admin/attribute-sets?status=inactive')
            ->assertOk()
            ->assertSee('Laptop')
            ->assertDontSee(route('admin.attribute-sets.edit', $smartphone), false);

        $this->actingAs($admin)->get('/admin/attribute-sets/create')->assertOk();
        $this->actingAs($admin)->get("/admin/attribute-sets/{$smartphone->id}/edit")->assertOk();
    }

    public function test_product_attribute_set_seeder_is_repeatable(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(AttributeSeeder::class);
        $this->seed(ProductAttributeSetSeeder::class);
        $this->seed(ProductAttributeSetSeeder::class);

        $this->assertSame(9, ProductAttributeSet::query()->count());
        $this->assertSame(
            ProductAttributeSet::query()->withCount('attributes')->get()->sum('attributes_count'),
            DB::table('product_attribute_set_attributes')->count(),
        );
        $this->assertSame(12, Category::query()->whereNotNull('product_attribute_set_id')->count());
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function attributeSetData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Smartphone',
            'code' => 'smartphone-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'Reusable catalog configuration.',
            'is_active' => '1',
            'sort_order' => 10,
            'attributes' => [],
        ], $overrides);
    }

    private function categoryData(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => null,
            'product_attribute_set_id' => null,
            'name' => 'Smartphones',
            'slug' => 'smartphones-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'A category for marketplace taxonomy.',
            'is_active' => '1',
            'sort_order' => 10,
            'seo_title' => 'Smartphones',
            'seo_description' => 'Browse smartphones in the marketplace.',
        ], $overrides);
    }
}
