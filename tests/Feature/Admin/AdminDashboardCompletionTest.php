<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorPayoutStatus;
use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrderCommission;
use App\Models\VendorPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_access_layout_and_navigation_badges(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Control Admin']);
        $vendorUser = User::factory()->vendor()->create();
        Vendor::factory()->approved()->create(['user_id' => $vendorUser->id]);
        $customer = User::factory()->customer()->create();

        Vendor::factory()->create(['status' => VendorStatus::Pending, 'submitted_at' => now()]);
        Product::factory()->create([
            'name' => 'Queued Dashboard Product',
            'approval_status' => ProductApprovalStatus::Pending,
            'status' => ProductStatus::Draft,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSeeText('Marketplace')
            ->assertSeeText('Commerce')
            ->assertSeeText('Finance')
            ->assertSeeText('Operations')
            ->assertSeeText('Vendors Awaiting Approval')
            ->assertSeeText('Products Awaiting Approval')
            ->assertSeeText('Queued Dashboard Product');

        $this->get('/admin')->assertOk();

        auth()->logout();

        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($vendorUser)->get('/admin')->assertForbidden();
    }

    public function test_dashboard_counts_use_real_operational_states(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'Dashboard Seller']);

        Vendor::factory()->create([
            'display_name' => 'Pending Seller',
            'status' => VendorStatus::Pending,
            'submitted_at' => now()->subHour(),
        ]);
        Vendor::factory()->create(['status' => VendorStatus::Draft]);

        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'name' => '<script>alert("admin")</script> Pending Product',
            'approval_status' => ProductApprovalStatus::Pending,
            'status' => ProductStatus::Draft,
            'submitted_at' => now()->subMinutes(30),
        ]);
        Product::factory()->approved()->create(['vendor_id' => $vendor->id]);

        $review = ProductReview::factory()->create([
            'status' => ProductReviewStatus::Pending,
            'reviewer_display_name' => 'Careful Customer',
        ]);

        $payment = $this->payment(requiresReview: true);
        $this->payment(requiresReview: true, resolved: true);

        Refund::factory()->create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'status' => RefundStatus::Processing,
            'amount' => 1200,
        ]);
        Refund::factory()->create(['status' => RefundStatus::Succeeded]);

        VendorPayout::factory()->forVendor($vendor)->create([
            'status' => VendorPayoutStatus::Approved,
            'amount' => 5000,
        ]);
        VendorPayout::factory()->forVendor($vendor)->paid()->create([
            'status' => VendorPayoutStatus::Paid,
            'amount' => 4000,
        ]);

        VendorOrderCommission::factory()->create([
            'original_commission_amount' => 2500,
            'currency' => 'USD',
        ]);
        VendorLedgerEntry::factory()->create([
            'vendor_id' => $vendor->id,
            'type' => VendorLedgerEntryType::Commission,
            'amount' => -2500,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Pending Seller')
            ->assertSeeText('Careful Customer')
            ->assertSeeText($payment->order->order_number)
            ->assertSeeText('Vendors Awaiting Approval')
            ->assertSeeText('Products Awaiting Approval')
            ->assertSeeText('Reviews Pending')
            ->assertSeeText('Payments Requiring Review')
            ->assertSeeText('Refunds Requiring Attention')
            ->assertSeeText('Payouts Awaiting Action')
            ->assertSeeText('USD 25.00')
            ->assertSee('&lt;script&gt;alert(&quot;admin&quot;)&lt;/script&gt; Pending Product', false)
            ->assertDontSee('<script>alert("admin")</script>', false);

        $this->assertSame(ProductReviewStatus::Pending, $review->refresh()->status);
    }

    public function test_payment_review_filter_and_attempt_count_render_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->payment(requiresReview: true);
        $clean = $this->payment();

        $payment->attempts()->create([
            'attempt_number' => 1,
            'status' => \App\Enums\PaymentAttemptStatus::Failed,
            'provider_reference' => 'pi_visible_reference',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'failure_code' => 'card_declined',
            'failure_message' => 'Payment failed.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['review' => 'required']))
            ->assertOk()
            ->assertSeeText($payment->order->order_number)
            ->assertSeeText('Review required')
            ->assertSeeText('1')
            ->assertDontSeeText($clean->order->order_number)
            ->assertDontSeeText('whsec_')
            ->assertDontSeeText('sk_live');
    }

    public function test_admin_dashboard_does_not_add_generic_mutation_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $entry = VendorLedgerEntry::factory()->create();

        $this->actingAs($admin)->post('/admin/orders/'.$order->order_number.'/status', [
            'status' => OrderStatus::Completed->value,
        ])->assertNotFound();

        $this->actingAs($admin)->put('/admin/vendor-ledger/'.$entry->id, [
            'amount' => 999999,
        ])->assertNotFound();
    }

    private function payment(bool $requiresReview = false, bool $resolved = false): Payment
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Paid,
            'currency' => 'USD',
            'items_subtotal' => 10000,
            'shipping_total' => 500,
            'grand_total' => 10500,
        ]);

        return Payment::factory()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Card,
            'provider' => 'stripe',
            'status' => PaymentStatus::Paid,
            'currency' => 'USD',
            'amount' => 10500,
            'provider_reference' => 'pi_dashboard_'.$order->id,
            'requires_review_at' => $requiresReview ? now()->subHour() : null,
            'review_reason' => $requiresReview ? 'Payment succeeded after order cancellation.' : null,
            'review_resolved_at' => $resolved ? now() : null,
        ])->load('order.user');
    }
}
