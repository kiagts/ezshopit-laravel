<?php

namespace Tests\Feature\Vendor;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorPayoutStatus;
use App\Enums\VendorStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorDashboardCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_dashboard_access_layout_and_noindex(): void
    {
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'Dashboard Store']);
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();

        $this->get(route('vendor.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($customer)->get(route('vendor.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('vendor.dashboard'))->assertForbidden();

        $this->actingAs($vendor->user)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('noindex,nofollow', false)
            ->assertSeeText('Dashboard Store')
            ->assertSeeText('Products')
            ->assertSeeText('Inventory')
            ->assertSeeText('Orders')
            ->assertSeeText('Promotions')
            ->assertSeeText('Reviews')
            ->assertSeeText('Earnings')
            ->assertSeeText('Commissions')
            ->assertSeeText('Payouts')
            ->assertSeeText('Notifications')
            ->assertSeeText('Store Profile');
    }

    public function test_dashboard_metrics_are_real_vendor_scoped_and_private(): void
    {
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'Alpha Store']);
        $otherVendor = Vendor::factory()->approved()->create(['display_name' => 'Beta Store']);

        $this->productWithInventory($vendor, ProductStatus::Active, ProductApprovalStatus::Approved, 3, 1);
        $this->productWithInventory($vendor, ProductStatus::Draft, ProductApprovalStatus::Draft, 10, 5);
        $this->productWithInventory($vendor, ProductStatus::Draft, ProductApprovalStatus::Pending, 10, 5);
        $this->productWithInventory($vendor, ProductStatus::Draft, ProductApprovalStatus::Rejected, 10, 5);
        $this->productWithInventory($otherVendor, ProductStatus::Active, ProductApprovalStatus::Approved, 1, 5);

        $ownOrder = $this->vendorOrder($vendor, VendorOrderStatus::Pending, 'VO-ALPHA', 'ORD-ALPHA', 'Alpha Customer', '10 Private Street');
        $this->vendorOrder($vendor, VendorOrderStatus::Shipped, 'VO-SHIPPED', 'ORD-SHIPPED', 'Waiting Customer', '20 Private Street');
        $this->vendorOrder($otherVendor, VendorOrderStatus::Pending, 'VO-BETA', 'ORD-BETA', 'Beta Customer', '99 Other Street');

        VendorLedgerEntry::factory()->create([
            'vendor_id' => $vendor->id,
            'type' => VendorLedgerEntryType::Sale,
            'amount' => 10000,
            'available_at' => now()->subDay(),
        ]);
        VendorLedgerEntry::factory()->create([
            'vendor_id' => $vendor->id,
            'type' => VendorLedgerEntryType::Sale,
            'amount' => 2500,
            'available_at' => now()->addDay(),
        ]);
        VendorLedgerEntry::factory()->create([
            'vendor_id' => $otherVendor->id,
            'type' => VendorLedgerEntryType::Sale,
            'amount' => 999999,
            'available_at' => now()->subDay(),
        ]);
        VendorPayout::factory()->forVendor($vendor)->create([
            'status' => VendorPayoutStatus::Pending,
            'amount' => 5000,
        ]);

        $this->storedNotification($vendor->user, 'Alpha notification');
        $this->storedNotification($otherVendor->user, 'Beta notification');

        $this->actingAs($vendor->user)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSeeText('VO-ALPHA')
            ->assertSeeText('ORD-ALPHA')
            ->assertSeeText('Alpha Customer')
            ->assertSeeText('Active Products')
            ->assertSeeText('USD 100.00')
            ->assertSeeText('USD 25.00')
            ->assertSeeText('USD 50.00')
            ->assertSeeText('Alpha notification')
            ->assertDontSeeText('VO-BETA')
            ->assertDontSeeText('ORD-BETA')
            ->assertDontSeeText('Beta Customer')
            ->assertDontSeeText('99 Other Street')
            ->assertDontSeeText('USD 9,999.99')
            ->assertDontSeeText('Beta notification');

        $this->assertSame($vendor->id, $ownOrder->vendor_id);
    }

    public function test_dashboard_uses_weighted_product_rating_and_does_not_label_it_seller_rating(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        Product::factory()->for($vendor)->create([
            'rating_sum' => 10,
            'rating_count' => 2,
        ]);
        Product::factory()->for($vendor)->create([
            'rating_sum' => 3,
            'rating_count' => 1,
        ]);

        $this->actingAs($vendor->user)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSeeText('Average Product Rating 4.3/5')
            ->assertDontSeeText('Seller Rating');
    }

    public function test_store_profile_updates_only_allowed_public_fields(): void
    {
        $vendor = Vendor::factory()->approved()->create([
            'display_name' => 'Original Store',
            'slug' => 'original-store',
            'description' => 'Original description.',
        ]);

        $this->actingAs($vendor->user)
            ->put(route('vendor.store.update'), [
                'display_name' => 'Updated Store',
                'description' => '<strong>Escaped store copy.</strong>',
                'email' => 'store@example.com',
                'phone' => '+92 300 1111111',
                'website' => 'https://example.com',
                'slug' => 'hacked-slug',
                'status' => VendorStatus::Suspended->value,
                'approved_at' => null,
            ])
            ->assertRedirect(route('vendor.store.edit'));

        $vendor->refresh();

        $this->assertSame('Updated Store', $vendor->display_name);
        $this->assertSame('original-store', $vendor->slug);
        $this->assertSame(VendorStatus::Approved, $vendor->status);

        $this->actingAs($vendor->user)
            ->get(route('vendor.store.edit'))
            ->assertOk()
            ->assertSeeText('Updated Store')
            ->assertSee('&lt;strong&gt;Escaped store copy.&lt;/strong&gt;', false)
            ->assertDontSee('<strong>Escaped store copy.</strong>', false);
    }

    public function test_suspended_vendor_cannot_access_approved_only_dashboard_or_store_profile(): void
    {
        $vendor = Vendor::factory()->create(['status' => VendorStatus::Suspended]);

        $this->actingAs($vendor->user)->get(route('vendor.dashboard'))->assertRedirect(route('vendor.application-status'));
        $this->actingAs($vendor->user)->get(route('vendor.store.edit'))->assertRedirect(route('vendor.application-status'));
    }

    private function productWithInventory(Vendor $vendor, ProductStatus $status, ProductApprovalStatus $approval, int $onHand, int $threshold): Product
    {
        $product = Product::factory()->for($vendor)->create([
            'status' => $status,
            'approval_status' => $approval,
        ]);
        $variant = ProductVariant::factory()->for($product)->createQuietly(['is_active' => true]);
        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'on_hand' => $onHand,
            'reserved' => 0,
            'low_stock_threshold' => $threshold,
        ]);

        return $product;
    }

    private function vendorOrder(Vendor $vendor, VendorOrderStatus $status, string $vendorOrderNumber, string $orderNumber, string $customerName, string $address): VendorOrder
    {
        [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, '');

        $order = Order::factory()->create([
            'order_number' => $orderNumber,
            'shipping_first_name' => $firstName,
            'shipping_last_name' => $lastName,
            'shipping_address_line_1' => $address,
        ]);
        $vendorOrder = VendorOrder::factory()->for($order)->for($vendor)->create([
            'vendor_order_number' => $vendorOrderNumber,
            'status' => $status,
            'grand_total' => 4000,
        ]);
        OrderItem::factory()->for($vendorOrder)->create([
            'quantity' => 2,
            'product_name' => 'Seller item',
        ]);

        return $vendorOrder;
    }

    private function storedNotification(User $user, string $title): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'database',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'test',
                'title' => $title,
                'message' => $title.' body.',
                'action_url' => '/vendor',
                'severity' => 'info',
                'source_key' => 'test:'.Str::uuid(),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
