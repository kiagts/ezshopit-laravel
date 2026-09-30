<?php

namespace Tests\Feature\Homepage;

use App\Enums\HomepageSectionType;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_homepage_preserves_current_landing_sections(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Shop smarter. Choose trusted.')
            ->assertSeeText('Latest Smartphones')
            ->assertSeeText('Shop by Category')
            ->assertSeeText('All in One Place')
            ->assertSeeText('Featured Products')
            ->assertSeeText('Top Brands You Love')
            ->assertSeeText('Featured Stores')
            ->assertSee('Marketplace Sale Promotion')
            ->assertSeeText('New Arrivals')
            ->assertSeeText('Transparent Pricing')
            ->assertSeeText('Ready to grow your business?');
    }

    public function test_homepage_respects_section_order_disabled_and_schedule(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $this->benefitSection('second-section', 'Second Visible', 20);
        $this->benefitSection('first-section', 'First Visible', 10);
        $this->benefitSection('disabled-section', 'Hidden Disabled', 30, ['is_enabled' => false]);
        $this->benefitSection('future-section', 'Hidden Future', 40, ['starts_at' => now()->addDay()]);
        $this->benefitSection('expired-section', 'Hidden Expired', 50, ['ends_at' => now()->subMinute()]);

        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder(['First Visible', 'Second Visible']);
        $response->assertDontSeeText('Hidden Disabled');
        $response->assertDontSeeText('Hidden Future');
        $response->assertDontSeeText('Hidden Expired');
    }

    public function test_hero_content_comes_from_database_and_escapes_html(): void
    {
        $section = HomepageSection::query()->create([
            'key' => 'hero',
            'type' => HomepageSectionType::Hero,
            'name' => 'Hero',
            'title' => '<script>alert("x")</script> Trusted hero',
            'subtitle' => 'Database hero body',
            'is_enabled' => true,
            'sort_order' => 10,
            'settings' => [
                'eyebrow' => 'CMS Hero',
                'primary_cta_label' => 'Shop',
                'primary_cta_url' => '/search',
                'benefits' => ['Safe content'],
            ],
        ]);
        $section->contentItems()->create([
            'title' => 'CMS Slide',
            'body' => 'Slide body',
            'image_path' => 'https://example.com/slide.jpg',
            'cta_url' => '/search',
            'sort_order' => 10,
            'settings' => ['group' => 0],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('CMS Hero')
            ->assertSeeText('<script>alert("x")</script> Trusted hero')
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_manual_product_section_preserves_order_and_filters_non_public_products(): void
    {
        [$visibleProduct, $hiddenProduct] = $this->publicAndHiddenProducts();

        $section = HomepageSection::query()->create([
            'key' => 'manual-products',
            'type' => HomepageSectionType::Products,
            'name' => 'Manual Products',
            'title' => 'Manual Products',
            'is_enabled' => true,
            'sort_order' => 10,
            'settings' => ['source' => 'manual', 'limit' => 8],
        ]);
        $section->items()->create(['item_type' => 'product', 'item_id' => $hiddenProduct->id, 'sort_order' => 10]);
        $section->items()->create(['item_type' => 'product', 'item_id' => $visibleProduct->id, 'sort_order' => 20]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText($visibleProduct->name)
            ->assertDontSeeText($hiddenProduct->name);
    }

    public function test_on_sale_product_section_only_renders_genuine_sale_products(): void
    {
        $saleProduct = $this->publicProduct('Sale Product');
        $regularProduct = $this->publicProduct('Regular Product');

        $saleProduct->variants()->first()->update(['base_price' => 10000, 'sale_price' => 8000]);
        $regularProduct->variants()->first()->update(['base_price' => 10000, 'sale_price' => null]);

        HomepageSection::query()->create([
            'key' => 'sale-products',
            'type' => HomepageSectionType::Products,
            'name' => 'Sale Products',
            'title' => 'Sale Products',
            'is_enabled' => true,
            'sort_order' => 10,
            'settings' => ['source' => 'on_sale', 'limit' => 8],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Sale Product')
            ->assertDontSeeText('Regular Product');
    }

    public function test_admin_only_homepage_manager_preview_toggle_and_reorder(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = User::factory()->vendor()->create();
        $section = $this->benefitSection('admin-section', 'Admin Managed', 10);

        $this->get('/admin/homepage')->assertRedirect('/login');
        $this->actingAs($vendor)->get('/admin/homepage')->assertForbidden();

        $this->actingAs($admin)->get('/admin/homepage')->assertOk()->assertSeeText('Admin Managed');
        $this->actingAs($admin)->get('/admin/homepage/preview')->assertOk()->assertSeeText('Admin Managed');

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.toggle', $section))
            ->assertRedirect();
        $this->assertFalse($section->fresh()->is_enabled);

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.reorder'), [
                'sections' => [
                    ['id' => $section->id, 'sort_order' => 90],
                ],
            ])
            ->assertRedirect();
        $this->assertSame(90, $section->fresh()->sort_order);
    }

    public function test_admin_section_validation_rejects_unsafe_urls_unknown_settings_and_tampered_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.store'), [
                'type' => 'hero',
                'key' => 'unsafe-hero',
                'name' => 'Unsafe Hero',
                'title' => 'Unsafe',
                'is_enabled' => '1',
                'settings' => [
                    'primary_cta_url' => 'javascript:alert(1)',
                ],
            ])
            ->assertSessionHasErrors('primary_cta_url');

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.store'), [
                'type' => 'hero',
                'key' => 'unknown-setting',
                'name' => 'Unknown Setting',
                'title' => 'Unknown',
                'is_enabled' => '1',
                'settings' => [
                    'raw_html' => '<strong>no</strong>',
                ],
            ])
            ->assertSessionHasErrors('settings');

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.store'), [
                'type' => 'raw_html',
                'key' => 'raw-html',
                'name' => 'Raw HTML',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_duplicate_entity_item_is_prevented_per_section_but_allowed_across_sections(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->publicProduct('Reusable Product');

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.store'), [
                'type' => 'products',
                'key' => 'first-products',
                'name' => 'First Products',
                'title' => 'First Products',
                'is_enabled' => '1',
                'settings' => ['source' => 'manual', 'limit' => 8],
                'item_ids' => [$product->id, $product->id],
            ])
            ->assertRedirect();

        $first = HomepageSection::query()->where('key', 'first-products')->firstOrFail();
        $this->assertSame(1, $first->items()->count());

        $this->actingAs($admin)
            ->post(route('admin.homepage.sections.store'), [
                'type' => 'products',
                'key' => 'second-products',
                'name' => 'Second Products',
                'title' => 'Second Products',
                'is_enabled' => '1',
                'settings' => ['source' => 'manual', 'limit' => 8],
                'item_ids' => [$product->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, HomepageSection::query()->whereIn('key', ['first-products', 'second-products'])->count());
    }

    public function test_editor_records_admin_and_accepts_end_only_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.homepage.sections.store'), [
            'type' => 'benefits', 'key' => 'end-only', 'name' => 'End only',
            'starts_at' => null, 'ends_at' => now()->addDay()->toDateTimeString(),
            'content_items' => [['title' => 'Delivery', 'icon_key' => 'truck']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $section = HomepageSection::where('key', 'end-only')->firstOrFail();
        $this->assertSame($admin->id, $section->created_by);
        $this->actingAs($admin)->put(route('admin.homepage.sections.update', $section), [
            'key' => 'end-only', 'name' => 'Edited',
        ])->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $section->fresh()->updated_by);
    }

    public function test_content_settings_and_category_source_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.homepage.sections.store'), [
            'type' => 'promotion_grid', 'key' => 'bad-content', 'name' => 'Bad',
            'content_items' => [['title' => 'Bad', 'settings' => ['eyebrow' => ['nested'], 'group' => 8]]],
        ])->assertSessionHasErrors(['content_items.0.settings.eyebrow', 'content_items.0.settings.group']);
        $this->post(route('admin.homepage.sections.store'), [
            'type' => 'products', 'key' => 'no-category', 'name' => 'Bad',
            'settings' => ['source' => 'category'],
        ])->assertSessionHasErrors('category_id');
        $this->post(route('admin.homepage.sections.store'), [
            'type' => 'hero', 'key' => 'optional-benefits', 'name' => 'Hero',
            'settings' => ['benefits' => ['One benefit', null, null]],
        ])->assertSessionHasNoErrors();
    }

    public function test_edit_options_keep_selected_entities_in_order_even_when_not_public(): void
    {
        $first = $this->publicProduct('Curated First');
        $second = $this->publicProduct('Curated Second');
        $first->update(['status' => ProductStatus::Inactive]);
        $section = HomepageSection::create(['key' => 'curated', 'type' => 'products', 'name' => 'Curated', 'settings' => ['source' => 'manual']]);
        $section->items()->create(['item_type' => 'product', 'item_id' => $first->id, 'sort_order' => 10]);
        $section->items()->create(['item_type' => 'product', 'item_id' => $second->id, 'sort_order' => 20]);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.homepage.sections.edit', $section))->assertOk();
        $response->assertSeeInOrder(['Curated First', 'Curated Second']);
        $response->assertSee('data-entity-types="products product_showcase ready_to_grow"', false);
    }

    public function test_manual_categories_respect_all_ancestors_and_missing_entities(): void
    {
        $root = Category::factory()->inactive()->create();
        $parent = Category::factory()->childOf($root)->create();
        $child = Category::factory()->childOf($parent)->create(['name' => 'Hidden Grandchild']);
        $section = HomepageSection::create(['key' => 'deep-category', 'type' => 'categories', 'name' => 'Deep', 'settings' => ['source' => 'manual']]);
        $section->items()->create(['item_type' => 'category', 'item_id' => $child->id]);
        $section->items()->create(['item_type' => 'category', 'item_id' => 999999]);
        $this->get('/')->assertOk()->assertDontSeeText('Hidden Grandchild');
    }

    public function test_banner_renders_saved_link_mobile_image_and_alt_and_omits_empty_slider(): void
    {
        $section = HomepageSection::create(['key' => 'banner', 'type' => 'banner_slider', 'name' => 'Banner']);
        $section->contentItems()->create(['title' => 'Campaign', 'image_path' => 'https://example.com/desktop.png', 'mobile_image_path' => 'https://example.com/mobile.png', 'image_alt' => 'Campaign artwork', 'cta_url' => '/search?q=campaign']);
        $this->get('/')->assertOk()->assertSee('href="/search?q=campaign"', false)->assertSee('https://example.com/mobile.png', false)->assertSee('Campaign artwork');
        $section->contentItems()->update(['image_path' => null, 'mobile_image_path' => null]);
        $this->get('/')->assertOk()->assertDontSee('data-banner-slider', false);
    }

    public function test_preview_bypasses_windows_but_keeps_entity_visibility_and_is_noindex(): void
    {
        $this->benefitSection('preview-only', 'Preview only content', 10, ['is_enabled' => false, 'starts_at' => now()->addDay()]);
        $this->get('/')->assertDontSeeText('Preview only content');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.homepage.preview'))
            ->assertOk()->assertSeeText('Preview only content')->assertSee('noindex,nofollow');
    }

    public function test_every_cms_endpoint_is_protected_from_customers_and_vendors(): void
    {
        $section = $this->benefitSection('protected', 'Protected', 10);
        $endpoints = [
            ['get', 'admin.homepage.index', []], ['get', 'admin.homepage.preview', []],
            ['get', 'admin.homepage.sections.create', []], ['post', 'admin.homepage.sections.store', []],
            ['get', 'admin.homepage.sections.edit', [$section]], ['put', 'admin.homepage.sections.update', [$section]],
            ['post', 'admin.homepage.sections.toggle', [$section]], ['post', 'admin.homepage.sections.reorder', []],
        ];
        foreach ([User::factory()->create(), User::factory()->vendor()->create()] as $user) {
            $this->actingAs($user);
            foreach ($endpoints as [$method, $route, $params]) {
                $this->{$method}(route($route, $params))->assertForbidden();
            }
        }
    }

    public function test_publish_boundaries_are_inclusive_and_invalid_windows_are_rejected(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->benefitSection('boundary', 'Boundary visible', 10, ['starts_at' => now(), 'ends_at' => now()]);
        $this->get('/')->assertSeeText('Boundary visible');
        $this->travel(1)->seconds();
        $this->get('/')->assertDontSeeText('Boundary visible');
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.homepage.sections.store'), [
            'type' => 'benefits', 'key' => 'bad-window', 'name' => 'Bad',
            'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->toDateTimeString(),
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_homepage_seeder_preserves_editor_changes_on_repeat_run(): void
    {
        $this->seed(\Database\Seeders\HomepageSeeder::class);
        $section = HomepageSection::where('key', 'hero')->firstOrFail();
        $section->update(['title' => 'Edited hero', 'is_enabled' => false]);
        $section->contentItems()->first()->update(['title' => 'Edited slide']);
        $this->seed(\Database\Seeders\HomepageSeeder::class);
        $this->assertSame('Edited hero', $section->fresh()->title);
        $this->assertFalse($section->fresh()->is_enabled);
        $this->assertSame('Edited slide', $section->contentItems()->first()->title);
        $this->assertSame(11, HomepageSection::count());
    }

    public function test_merchandising_uses_real_counts_and_does_not_invent_badges(): void
    {
        $product = $this->publicProduct('Actual Product');
        HomepageSection::create(['key' => 'stores', 'name' => 'Stores', 'type' => 'vendors', 'settings' => ['source' => 'latest']]);
        for ($i = 0; $i < 3; $i++) {
            $this->publicProduct('Ordinary Product '.$i);
        }
        HomepageSection::create(['key' => 'products', 'name' => 'Products', 'type' => 'products', 'settings' => ['source' => 'latest']]);
        $this->get('/')->assertOk()->assertDontSeeText('Bestseller');
        $sections = app(\App\Services\Homepage\HomepageService::class)->build()['homepageSections'];
        $this->assertSame(1, $sections->firstWhere('key', 'stores')['vendors']->firstWhere('id', $product->vendor_id)->products_count);
    }

    private function benefitSection(string $key, string $title, int $sortOrder, array $overrides = []): HomepageSection
    {
        $section = HomepageSection::query()->create(array_merge([
            'key' => $key,
            'type' => HomepageSectionType::Benefits,
            'name' => $title,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'settings' => [],
        ], $overrides));

        $section->contentItems()->create([
            'title' => $title,
            'body' => 'Benefit body',
            'icon_key' => 'shield',
            'sort_order' => 10,
        ]);

        return $section;
    }

    private function publicProduct(string $name): Product
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::factory()->create(['is_active' => true]);

        $product = Product::factory()
            ->for($vendor)
            ->for($category)
            ->approved()
            ->create([
                'name' => $name,
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Approved,
                'published_at' => now(),
            ]);

        ProductVariant::factory()
            ->for($product)
            ->default()
            ->create(['base_price' => 10000, 'sale_price' => null, 'is_active' => true]);

        return $product;
    }

    /**
     * @return array{0: Product, 1: Product}
     */
    private function publicAndHiddenProducts(): array
    {
        $visible = $this->publicProduct('Visible Public Product');

        $vendor = Vendor::factory()->approved()->create(['status' => VendorStatus::Suspended]);
        $category = Category::factory()->create(['is_active' => true]);
        $hidden = Product::factory()
            ->for($vendor)
            ->for($category)
            ->approved()
            ->create([
                'name' => 'Suspended Vendor Product',
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Approved,
                'published_at' => now(),
            ]);
        ProductVariant::factory()->for($hidden)->default()->create(['base_price' => 10000, 'is_active' => true]);

        return [$visible, $hidden];
    }
}
