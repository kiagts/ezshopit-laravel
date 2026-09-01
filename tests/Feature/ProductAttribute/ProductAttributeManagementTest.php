<?php

namespace Tests\Feature\ProductAttribute;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_create_edit_and_delete_attribute_without_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/attributes')->assertOk();
        $this->actingAs($admin)->get('/admin/attributes/create')->assertOk();

        $this->actingAs($admin)
            ->post('/admin/attributes', $this->attributeData([
                'name' => 'Battery Capacity',
                'code' => '',
                'type' => ProductAttributeType::Specification->value,
                'input_type' => ProductAttributeInputType::Number->value,
                'is_filterable' => '1',
                'is_required' => '1',
                'is_active' => '0',
                'sort_order' => 90,
            ]))
            ->assertRedirect();

        $attribute = ProductAttribute::query()->where('code', 'battery_capacity')->firstOrFail();
        $this->assertSame(ProductAttributeType::Specification, $attribute->type);
        $this->assertSame(ProductAttributeInputType::Number, $attribute->input_type);
        $this->assertTrue($attribute->is_filterable);
        $this->assertTrue($attribute->is_required);
        $this->assertFalse($attribute->is_active);
        $this->assertSame(90, $attribute->sort_order);

        $this->actingAs($admin)->get("/admin/attributes/{$attribute->id}/edit")->assertOk();

        $this->actingAs($admin)
            ->delete("/admin/attributes/{$attribute->id}")
            ->assertRedirect('/admin/attributes');

        $this->assertSoftDeleted($attribute);
    }

    public function test_vendor_customer_and_guest_cannot_manage_attributes(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => VendorStatus::Approved]);

        $this->get('/admin/attributes')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/attributes')->assertForbidden();
        $this->actingAs($vendorUser)->get('/admin/attributes')->assertForbidden();
    }

    public function test_manual_code_duplicate_code_and_generated_collision_behavior(): void
    {
        $admin = $this->admin();
        ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color']);

        $this->actingAs($admin)
            ->post('/admin/attributes', $this->attributeData(['name' => 'Color', 'code' => '']))
            ->assertRedirect();

        $this->assertDatabaseHas('product_attributes', ['code' => 'color_2']);

        $this->actingAs($admin)
            ->post('/admin/attributes', $this->attributeData([
                'name' => 'Screen Size',
                'code' => 'Screen Size',
                'type' => ProductAttributeType::Specification->value,
                'input_type' => ProductAttributeInputType::Text->value,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('product_attributes', ['code' => 'screen_size']);

        $this->actingAs($admin)
            ->from('/admin/attributes/create')
            ->post('/admin/attributes', $this->attributeData(['name' => 'Other Color', 'code' => 'color']))
            ->assertRedirect('/admin/attributes/create')
            ->assertSessionHasErrors('code');
    }

    public function test_variant_attributes_must_use_select_input(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/attributes', $this->attributeData([
                'type' => ProductAttributeType::Variant->value,
                'input_type' => ProductAttributeInputType::Select->value,
            ]))
            ->assertRedirect();

        foreach ([
            ProductAttributeInputType::Text,
            ProductAttributeInputType::Number,
            ProductAttributeInputType::Boolean,
            ProductAttributeInputType::Multiselect,
        ] as $inputType) {
            $this->actingAs($admin)
                ->from('/admin/attributes/create')
                ->post('/admin/attributes', $this->attributeData([
                    'name' => 'Variant '.$inputType->value,
                    'code' => 'variant_'.$inputType->value,
                    'type' => ProductAttributeType::Variant->value,
                    'input_type' => $inputType->value,
                ]))
                ->assertRedirect('/admin/attributes/create')
                ->assertSessionHasErrors('input_type');
        }
    }

    public function test_attribute_values_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $attribute = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color']);

        $this->actingAs($admin)
            ->post("/admin/attributes/{$attribute->id}/values", $this->valueData([
                'value' => 'Midnight Blue',
                'code' => '',
                'hex_color' => '#1e3a5f',
                'sort_order' => 20,
            ]))
            ->assertRedirect();

        $value = ProductAttributeValue::query()->where('code', 'midnight-blue')->firstOrFail();
        $this->assertSame($attribute->id, $value->product_attribute_id);
        $this->assertSame(['hex' => '#1E3A5F'], $value->metadata);
        $this->assertTrue($value->is_active);
        $this->assertSame(20, $value->sort_order);

        $this->actingAs($admin)
            ->put("/admin/attributes/{$attribute->id}/values/{$value->id}", $this->valueData([
                'value' => 'Navy Blue',
                'code' => 'Navy Blue',
                'hex_color' => '#1e40af',
                'is_active' => '0',
                'sort_order' => 30,
            ]))
            ->assertRedirect();

        $value->refresh();
        $this->assertSame('Navy Blue', $value->value);
        $this->assertSame('navy-blue', $value->code);
        $this->assertSame(['hex' => '#1E40AF'], $value->metadata);
        $this->assertFalse($value->is_active);
        $this->assertSame(30, $value->sort_order);

        $this->actingAs($admin)
            ->delete("/admin/attributes/{$attribute->id}/values/{$value->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($value);
    }

    public function test_attribute_value_duplicates_are_rejected_within_attribute(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['code' => 'color']);
        $size = ProductAttribute::factory()->variant()->create(['code' => 'size']);
        ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']);

        $this->actingAs($admin)
            ->from("/admin/attributes/{$color->id}/edit")
            ->post("/admin/attributes/{$color->id}/values", $this->valueData(['value' => 'Black', 'code' => 'black-2']))
            ->assertRedirect("/admin/attributes/{$color->id}/edit")
            ->assertSessionHasErrors('value');

        $this->actingAs($admin)
            ->from("/admin/attributes/{$color->id}/edit")
            ->post("/admin/attributes/{$color->id}/values", $this->valueData(['value' => 'Blackish', 'code' => 'black']))
            ->assertRedirect("/admin/attributes/{$color->id}/edit")
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post("/admin/attributes/{$size->id}/values", $this->valueData(['value' => 'Black', 'code' => 'black']))
            ->assertRedirect();

        $this->assertDatabaseHas('product_attribute_values', ['product_attribute_id' => $size->id, 'code' => 'black']);
    }

    public function test_value_ownership_is_enforced(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['code' => 'color']);
        $size = ProductAttribute::factory()->variant()->create(['code' => 'size']);
        $sizeValue = ProductAttributeValue::factory()->create(['product_attribute_id' => $size->id]);

        $this->actingAs($admin)
            ->put("/admin/attributes/{$color->id}/values/{$sizeValue->id}", $this->valueData())
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete("/admin/attributes/{$color->id}/values/{$sizeValue->id}")
            ->assertNotFound();
    }

    public function test_select_attribute_with_values_cannot_be_changed_to_free_input_type(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['code' => 'color']);
        ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id]);

        $this->actingAs($admin)
            ->from("/admin/attributes/{$color->id}/edit")
            ->put("/admin/attributes/{$color->id}", $this->attributeData([
                'name' => 'Color',
                'code' => 'color',
                'type' => ProductAttributeType::Specification->value,
                'input_type' => ProductAttributeInputType::Text->value,
            ]))
            ->assertRedirect("/admin/attributes/{$color->id}/edit")
            ->assertSessionHasErrors('input_type');

        $empty = ProductAttribute::factory()->create([
            'code' => 'material',
            'input_type' => ProductAttributeInputType::Select,
        ]);

        $this->actingAs($admin)
            ->put("/admin/attributes/{$empty->id}", $this->attributeData([
                'name' => 'Material',
                'code' => 'material',
                'type' => ProductAttributeType::Specification->value,
                'input_type' => ProductAttributeInputType::Text->value,
            ]))
            ->assertRedirect();

        $this->assertSame(ProductAttributeInputType::Text, $empty->refresh()->input_type);
    }

    public function test_attribute_with_values_cannot_be_deleted_until_values_are_removed(): void
    {
        $admin = $this->admin();
        $attribute = ProductAttribute::factory()->variant()->create();
        $value = ProductAttributeValue::factory()->create(['product_attribute_id' => $attribute->id]);

        $this->actingAs($admin)
            ->from("/admin/attributes/{$attribute->id}/edit")
            ->delete("/admin/attributes/{$attribute->id}")
            ->assertRedirect("/admin/attributes/{$attribute->id}/edit")
            ->assertSessionHasErrors('attribute');

        $this->actingAs($admin)->delete("/admin/attributes/{$attribute->id}/values/{$value->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/admin/attributes/{$attribute->id}")->assertRedirect('/admin/attributes');

        $this->assertSoftDeleted($attribute);
    }

    public function test_search_and_filters_work(): void
    {
        $admin = $this->admin();
        $color = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color', 'is_active' => true]);
        $material = ProductAttribute::factory()->create([
            'name' => 'Material',
            'code' => 'material',
            'type' => ProductAttributeType::Specification,
            'input_type' => ProductAttributeInputType::Select,
            'is_active' => false,
        ]);

        $this->actingAs($admin)->get('/admin/attributes?q=color')
            ->assertOk()
            ->assertSee('Color')
            ->assertDontSee(route('admin.attributes.edit', $material), false);

        $this->actingAs($admin)->get('/admin/attributes?q=material')
            ->assertOk()
            ->assertSee('Material')
            ->assertDontSee(route('admin.attributes.edit', $color), false);

        $this->actingAs($admin)->get('/admin/attributes?type=variant')
            ->assertOk()
            ->assertSee('Color')
            ->assertDontSee(route('admin.attributes.edit', $material), false);

        $this->actingAs($admin)->get('/admin/attributes?type=specification')
            ->assertOk()
            ->assertSee('Material')
            ->assertDontSee(route('admin.attributes.edit', $color), false);

        $this->actingAs($admin)->get('/admin/attributes?input_type=select')
            ->assertOk()
            ->assertSee('Color')
            ->assertSee('Material');

        $this->actingAs($admin)->get('/admin/attributes?status=active')
            ->assertOk()
            ->assertSee('Color')
            ->assertDontSee(route('admin.attributes.edit', $material), false);

        $this->actingAs($admin)->get('/admin/attributes?status=inactive')
            ->assertOk()
            ->assertSee('Material')
            ->assertDontSee(route('admin.attributes.edit', $color), false);
    }

    public function test_attribute_pages_render_for_admin(): void
    {
        $admin = $this->admin();
        $attribute = ProductAttribute::factory()->variant()->create();

        $this->actingAs($admin)->get('/admin/attributes')->assertOk();
        $this->actingAs($admin)->get('/admin/attributes/create')->assertOk();
        $this->actingAs($admin)->get("/admin/attributes/{$attribute->id}/edit")->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function attributeData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Color',
            'code' => 'color-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductAttributeType::Variant->value,
            'input_type' => ProductAttributeInputType::Select->value,
            'is_filterable' => '1',
            'is_required' => '0',
            'is_active' => '1',
            'sort_order' => 10,
        ], $overrides);
    }

    private function valueData(array $overrides = []): array
    {
        return array_merge([
            'value' => 'Black',
            'code' => 'black-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => 10,
            'is_active' => '1',
            'hex_color' => '',
        ], $overrides);
    }
}
