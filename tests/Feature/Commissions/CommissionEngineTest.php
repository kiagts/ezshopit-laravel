<?php

namespace Tests\Feature\Commissions;

use App\Actions\Commissions\ApplyRefundCommissionAdjustment;
use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Enums\CommissionRuleScope;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Enums\VendorOrderStatus;
use App\Models\CommissionAdjustment;
use App\Models\CommissionRule;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\RefundAllocation;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_rule_recognizes_delivered_collected_vendor_order_and_excludes_shipping_by_default(): void
    {
        $this->globalRule(rate: 1000, includeShipping: false);
        [$vendorOrder] = $this->paidVendorOrder(items: 10000, shipping: 500);

        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);

        $this->assertNotNull($commission);
        $this->assertSame(1000, $commission->rate_basis_points);
        $this->assertFalse($commission->include_shipping);
        $this->assertSame(10000, $commission->original_items_amount);
        $this->assertSame(500, $commission->original_shipping_amount);
        $this->assertSame(10000, $commission->original_commissionable_amount);
        $this->assertSame(1000, $commission->original_commission_amount);
        $this->assertSame('USD', $commission->currency);
    }

    public function test_vendor_override_takes_precedence_and_rate_snapshot_does_not_change_historical_commissions(): void
    {
        $globalRule = $this->globalRule(rate: 1000);
        $vendor = Vendor::factory()->approved()->create();
        CommissionRule::factory()->vendor($vendor)->create(['rate_basis_points' => 800]);
        [$firstVendorOrder] = $this->paidVendorOrder(vendor: $vendor, items: 10000);

        $firstCommission = app(RecognizeVendorOrderCommission::class)->handle($firstVendorOrder);
        $globalRule->forceFill(['rate_basis_points' => 1500])->save();
        [$secondVendorOrder] = $this->paidVendorOrder(items: 10000);
        $secondCommission = app(RecognizeVendorOrderCommission::class)->handle($secondVendorOrder);

        $this->assertSame(800, $firstCommission->rate_basis_points);
        $this->assertSame(800, $firstCommission->original_commission_amount);
        $this->assertSame(1500, $secondCommission->rate_basis_points);
        $this->assertSame(1500, $secondCommission->original_commission_amount);
        $this->assertSame(800, $firstCommission->refresh()->rate_basis_points);
    }

    public function test_shipping_can_be_included_by_snapshot_rule(): void
    {
        $this->globalRule(rate: 1000, includeShipping: true);
        [$vendorOrder] = $this->paidVendorOrder(items: 10000, shipping: 500);

        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);

        $this->assertTrue($commission->include_shipping);
        $this->assertSame(10500, $commission->original_commissionable_amount);
        $this->assertSame(1050, $commission->original_commission_amount);
    }

    public function test_ineligible_vendor_orders_are_not_recognized(): void
    {
        $this->globalRule();
        [$pendingVendorOrder] = $this->paidVendorOrder(status: VendorOrderStatus::Processing, paymentStatus: PaymentStatus::Paid);
        [$unpaidCodVendorOrder] = $this->paidVendorOrder(status: VendorOrderStatus::Delivered, paymentStatus: PaymentStatus::Pending, method: PaymentMethod::CashOnDelivery);
        [$cancelledVendorOrder] = $this->paidVendorOrder(status: VendorOrderStatus::Cancelled, paymentStatus: PaymentStatus::Paid);

        $this->assertNull(app(RecognizeVendorOrderCommission::class)->handle($pendingVendorOrder));
        $this->assertNull(app(RecognizeVendorOrderCommission::class)->handle($unpaidCodVendorOrder));
        $this->assertNull(app(RecognizeVendorOrderCommission::class)->handle($cancelledVendorOrder));
        $this->assertDatabaseCount('vendor_order_commissions', 0);
    }

    public function test_commission_recognition_is_idempotent(): void
    {
        $this->globalRule();
        [$vendorOrder] = $this->paidVendorOrder();

        app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        app(RecognizeVendorOrderCommission::class)->handle($vendorOrder->refresh());

        $this->assertDatabaseCount('vendor_order_commissions', 1);
    }

    public function test_pre_recognition_refund_reduces_initial_commission_without_adjustment(): void
    {
        $this->globalRule(rate: 1000);
        [$vendorOrder, $payment] = $this->paidVendorOrder(items: 10000);
        $this->successfulRefundAllocation($vendorOrder, $payment, amount: 2000, processedAt: now()->subMinute());

        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);

        $this->assertSame(8000, $commission->original_commissionable_amount);
        $this->assertSame(800, $commission->original_commission_amount);
        $this->assertDatabaseCount('commission_adjustments', 0);
    }

    public function test_post_recognition_item_refund_creates_one_negative_adjustment(): void
    {
        $this->globalRule(rate: 1000);
        [$vendorOrder, $payment] = $this->paidVendorOrder(items: 10000);
        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $allocation = $this->successfulRefundAllocation($vendorOrder, $payment, amount: 2500, processedAt: $commission->recognized_at->copy()->addMinute());

        app(ApplyRefundCommissionAdjustment::class)->handle($allocation);
        app(ApplyRefundCommissionAdjustment::class)->handle($allocation);

        $adjustment = CommissionAdjustment::query()->firstOrFail();
        $this->assertSame($commission->id, $adjustment->vendor_order_commission_id);
        $this->assertSame($allocation->id, $adjustment->refund_allocation_id);
        $this->assertSame(-2500, $adjustment->commissionable_amount_delta);
        $this->assertSame(-250, $adjustment->commission_amount_delta);
        $this->assertDatabaseCount('commission_adjustments', 1);
    }

    public function test_refund_adjustments_ignore_pending_failed_marketplace_and_excluded_shipping_allocations(): void
    {
        $this->globalRule(rate: 1000, includeShipping: false);
        [$vendorOrder, $payment] = $this->paidVendorOrder(items: 10000, shipping: 500);
        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);

        foreach ([RefundStatus::Pending, RefundStatus::Failed] as $status) {
            $allocation = $this->refundAllocation($vendorOrder, $payment, amount: 1000, status: $status, type: RefundAllocationType::Item, processedAt: $commission->recognized_at->copy()->addMinute());
            app(ApplyRefundCommissionAdjustment::class)->handle($allocation);
        }

        $shippingAllocation = $this->successfulRefundAllocation($vendorOrder, $payment, amount: 500, type: RefundAllocationType::Shipping, processedAt: $commission->recognized_at->copy()->addMinute());
        app(ApplyRefundCommissionAdjustment::class)->handle($shippingAllocation);

        $marketplaceAllocation = $this->refundAllocation($vendorOrder, $payment, amount: 1000, status: RefundStatus::Succeeded, type: RefundAllocationType::Adjustment, processedAt: $commission->recognized_at->copy()->addMinute(), sellerAttributed: false);
        app(ApplyRefundCommissionAdjustment::class)->handle($marketplaceAllocation);

        $this->assertDatabaseCount('commission_adjustments', 0);
    }

    public function test_shipping_refund_affects_commission_when_snapshot_includes_shipping(): void
    {
        $this->globalRule(rate: 1000, includeShipping: true);
        [$vendorOrder, $payment] = $this->paidVendorOrder(items: 10000, shipping: 500);
        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $allocation = $this->successfulRefundAllocation($vendorOrder, $payment, amount: 500, type: RefundAllocationType::Shipping, processedAt: $commission->recognized_at->copy()->addMinute());

        app(ApplyRefundCommissionAdjustment::class)->handle($allocation);

        $this->assertSame(-500, CommissionAdjustment::query()->value('commissionable_amount_delta'));
        $this->assertSame(-50, CommissionAdjustment::query()->value('commission_amount_delta'));
    }

    public function test_admin_can_manage_commission_rules_and_non_admins_cannot(): void
    {
        $this->globalRule();
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->approved()->create();

        $this->actingAs($admin)
            ->put(route('admin.commission-rules.global.update'), ['rate' => '12.25', 'include_shipping' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('commission_rules', [
            'scope' => CommissionRuleScope::Global->value,
            'rate_basis_points' => 1225,
            'include_shipping' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.commission-rules.index'))
            ->post(route('admin.commission-rules.vendors.store'), ['vendor_id' => $vendor->id, 'rate' => '101'])
            ->assertSessionHasErrors('rate');

        $this->actingAs($admin)
            ->post(route('admin.commission-rules.vendors.store'), ['vendor_id' => $vendor->id, 'rate' => '8.50'])
            ->assertRedirect();

        $this->assertDatabaseHas('commission_rules', [
            'scope' => CommissionRuleScope::Vendor->value,
            'vendor_id' => $vendor->id,
            'rate_basis_points' => 850,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.commission-rules.index'))
            ->assertForbidden();
    }

    public function test_admin_and_vendor_commission_pages_are_scoped(): void
    {
        $this->globalRule();
        [$vendorOrder] = $this->paidVendorOrder();
        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $otherVendor = Vendor::factory()->approved()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.commissions.show', $commission))
            ->assertOk()
            ->assertSeeText($vendorOrder->vendor_order_number);

        $this->actingAs($vendorOrder->vendor->user)
            ->get(route('vendor.commissions.index'))
            ->assertOk()
            ->assertSeeText($vendorOrder->vendor_order_number);

        $this->actingAs($otherVendor->user)
            ->get(route('vendor.commissions.index'))
            ->assertOk()
            ->assertDontSeeText($vendorOrder->vendor_order_number);

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('vendor.commissions.index'))
            ->assertForbidden();
    }

    public function test_reconciliation_command_recognizes_missing_commissions_and_repairs_missing_refund_adjustments(): void
    {
        $this->globalRule(rate: 1000);
        [$missingCommissionOrder] = $this->paidVendorOrder(items: 10000);
        [$refundedVendorOrder, $payment] = $this->paidVendorOrder(items: 10000);
        $commission = app(RecognizeVendorOrderCommission::class)->handle($refundedVendorOrder);
        $this->successfulRefundAllocation($refundedVendorOrder, $payment, amount: 1000, processedAt: $commission->recognized_at->copy()->addMinute());

        $this->artisan('commissions:reconcile')
            ->expectsOutput('Recognized commissions: 1')
            ->expectsOutput('Created refund adjustments: 1')
            ->assertSuccessful();

        $this->assertTrue($missingCommissionOrder->commission()->exists());
        $this->assertDatabaseCount('commission_adjustments', 1);
    }

    public function test_commission_calculator_uses_integer_basis_point_rounding(): void
    {
        $calculator = app(CommissionCalculator::class);

        $this->assertSame(1000, $calculator->parseBasisPoints('10'));
        $this->assertSame(1255, $calculator->parseBasisPoints('12.55'));
        $this->assertSame(1, $calculator->calculate(1, 5000));
        $this->assertSame(33, $calculator->calculate(999, 333));
    }

    private function globalRule(int $rate = 1000, bool $includeShipping = false): CommissionRule
    {
        return CommissionRule::factory()->create([
            'scope' => CommissionRuleScope::Global,
            'vendor_id' => null,
            'rate_basis_points' => $rate,
            'include_shipping' => $includeShipping,
            'is_active' => true,
        ]);
    }

    private function paidVendorOrder(?Vendor $vendor = null, int $items = 10000, int $shipping = 500, VendorOrderStatus $status = VendorOrderStatus::Delivered, PaymentStatus $paymentStatus = PaymentStatus::Paid, PaymentMethod $method = PaymentMethod::Card): array
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id]);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => $status === VendorOrderStatus::Delivered ? OrderStatus::Completed : OrderStatus::Processing,
            'payment_status' => $paymentStatus,
            'currency' => 'USD',
            'items_subtotal' => $items,
            'shipping_total' => $shipping,
            'grand_total' => $items + $shipping,
            'placed_at' => now()->subDay(),
        ]);

        $vendor ??= Vendor::factory()->approved()->create();
        $shippingMethod = ShippingMethod::factory()->create(['vendor_id' => $vendor->id]);
        $vendorOrder = VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'shipping_method_id' => $shippingMethod->id,
            'status' => $status,
            'items_subtotal' => $items,
            'shipping_total' => $shipping,
            'grand_total' => $items + $shipping,
            'delivered_at' => $status === VendorOrderStatus::Delivered ? now()->subHour() : null,
            'cancelled_at' => $status === VendorOrderStatus::Cancelled ? now()->subHour() : null,
        ]);

        $product = Product::factory()->approved()->create(['vendor_id' => $vendor->id]);
        $variant = ProductVariant::factory()->default()->create(['product_id' => $product->id, 'base_price' => $items]);
        OrderItem::factory()->create([
            'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => $items,
            'line_total' => $items,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method' => $method,
            'provider' => $method === PaymentMethod::Card ? 'stripe' : PaymentMethod::CashOnDelivery->value,
            'status' => $paymentStatus,
            'currency' => 'USD',
            'amount' => $items + $shipping,
            'provider_reference' => $method === PaymentMethod::Card ? 'pi_commission_'.$order->id : null,
            'paid_at' => in_array($paymentStatus, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true) ? now() : null,
        ]);

        return [$vendorOrder->load(['order.payment', 'vendor.user', 'items']), $payment, $order];
    }

    private function successfulRefundAllocation(VendorOrder $vendorOrder, Payment $payment, int $amount, RefundAllocationType $type = RefundAllocationType::Item, ?\DateTimeInterface $processedAt = null): RefundAllocation
    {
        return $this->refundAllocation($vendorOrder, $payment, $amount, RefundStatus::Succeeded, $type, $processedAt);
    }

    private function refundAllocation(VendorOrder $vendorOrder, Payment $payment, int $amount, RefundStatus $status, RefundAllocationType $type, ?\DateTimeInterface $processedAt = null, bool $sellerAttributed = true): RefundAllocation
    {
        $refund = Refund::factory()->create([
            'order_id' => $vendorOrder->order_id,
            'payment_id' => $payment->id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'provider_reference' => $status === RefundStatus::Succeeded ? 're_commission_'.$vendorOrder->id.'_'.str()->random(6) : null,
            'processed_at' => $status === RefundStatus::Succeeded ? ($processedAt ?? now()) : null,
            'failed_at' => $status === RefundStatus::Failed ? now() : null,
        ]);

        return $refund->allocations()->create([
            'vendor_order_id' => $sellerAttributed ? $vendorOrder->id : null,
            'order_item_id' => $type === RefundAllocationType::Item && $sellerAttributed ? $vendorOrder->items->first()->id : null,
            'type' => $type,
            'amount' => $amount,
            'quantity' => $type === RefundAllocationType::Item ? 1 : null,
        ]);
    }
}
