<?php

namespace Tests\Feature\VendorLedger;

use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Actions\Refunds\MarkRefundSucceeded;
use App\Actions\VendorLedger\PostVendorRefundDebit;
use App\Enums\CommissionRuleScope;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorOrderStatus;
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
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Services\VendorLedger\VendorEarningsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VendorLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_recognized_commission_posts_sale_and_commission_entries_with_same_hold_date(): void
    {
        config(['marketplace.vendor_earnings_hold_days' => 7]);
        $this->globalRule(rate: 1000, includeShipping: false);
        [$vendorOrder] = $this->paidVendorOrder(items: 10000, shipping: 500, deliveredAt: now()->subDay());

        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $entries = VendorLedgerEntry::query()->orderBy('id')->get();

        $this->assertCount(2, $entries);
        $this->assertSame(VendorLedgerEntryType::Sale, $entries[0]->type);
        $this->assertSame(10500, $entries[0]->amount);
        $this->assertSame(VendorLedgerEntryType::Commission, $entries[1]->type);
        $this->assertSame(-1000, $entries[1]->amount);
        $this->assertTrue($entries[0]->available_at->equalTo($entries[1]->available_at));
        $this->assertTrue($entries[0]->available_at->equalTo($vendorOrder->delivered_at->copy()->addDays(7)));
        $this->assertSame('vendor-order:'.$vendorOrder->id.':sale', $entries[0]->source_key);
        $this->assertSame('vendor-order-commission:'.$commission->id.':commission', $entries[1]->source_key);
    }

    public function test_hold_change_does_not_rewrite_existing_ledger_availability_and_idempotency_holds(): void
    {
        config(['marketplace.vendor_earnings_hold_days' => 7]);
        $this->globalRule();
        [$vendorOrder] = $this->paidVendorOrder(deliveredAt: now()->subDay());
        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $originalAvailableAt = VendorLedgerEntry::query()->where('type', VendorLedgerEntryType::Sale)->value('available_at');

        config(['marketplace.vendor_earnings_hold_days' => 14]);
        app(RecognizeVendorOrderCommission::class)->handle($vendorOrder->refresh());

        $this->assertDatabaseCount('vendor_ledger_entries', 2);
        $this->assertEquals($originalAvailableAt, VendorLedgerEntry::query()->where('source_key', 'vendor-order-commission:'.$commission->id.':commission')->value('available_at'));
    }

    public function test_ineligible_orders_do_not_post_ledger_entries(): void
    {
        $this->globalRule();
        [$pending] = $this->paidVendorOrder(status: VendorOrderStatus::Processing, paymentStatus: PaymentStatus::Paid);
        [$unpaidCod] = $this->paidVendorOrder(status: VendorOrderStatus::Delivered, paymentStatus: PaymentStatus::Pending, method: PaymentMethod::CashOnDelivery);
        [$cancelled] = $this->paidVendorOrder(status: VendorOrderStatus::Cancelled, paymentStatus: PaymentStatus::Paid);

        app(RecognizeVendorOrderCommission::class)->handle($pending);
        app(RecognizeVendorOrderCommission::class)->handle($unpaidCod);
        app(RecognizeVendorOrderCommission::class)->handle($cancelled);

        $this->assertDatabaseCount('vendor_ledger_entries', 0);
    }

    public function test_successful_refund_posts_refund_debit_and_commission_refund_credit_once(): void
    {
        $this->globalRule(rate: 1000);
        [$vendorOrder, $payment] = $this->paidVendorOrder(items: 10000, shipping: 500);
        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $refund = $this->pendingRefund($vendorOrder, $payment, amount: 2000, processedAt: $commission->recognized_at->copy()->addMinute());

        app(MarkRefundSucceeded::class)->handle($refund, 're_ledger_1');
        app(MarkRefundSucceeded::class)->handle($refund->refresh(), 're_ledger_1');

        $this->assertDatabaseHas('vendor_ledger_entries', [
            'type' => VendorLedgerEntryType::Refund->value,
            'amount' => -2000,
            'refund_allocation_id' => $refund->allocations()->value('id'),
        ]);
        $this->assertDatabaseHas('vendor_ledger_entries', [
            'type' => VendorLedgerEntryType::CommissionRefund->value,
            'amount' => 200,
        ]);
        $this->assertSame(4, VendorLedgerEntry::query()->count());
    }

    public function test_pending_failed_and_marketplace_refunds_do_not_post_vendor_refund_debits(): void
    {
        $this->globalRule();
        [$vendorOrder, $payment] = $this->paidVendorOrder();

        foreach ([RefundStatus::Pending, RefundStatus::Failed] as $status) {
            $allocation = $this->refundAllocation($vendorOrder, $payment, 1000, $status, RefundAllocationType::Item);
            $this->assertNull(app(PostVendorRefundDebit::class)->handle($allocation));
        }

        $marketplace = $this->refundAllocation($vendorOrder, $payment, 1000, RefundStatus::Succeeded, RefundAllocationType::Adjustment, sellerAttributed: false);
        $this->assertNull(app(PostVendorRefundDebit::class)->handle($marketplace));
        $this->assertDatabaseCount('vendor_ledger_entries', 0);
    }

    public function test_pre_recognition_refund_reconciles_to_gross_sale_refund_and_actual_commission(): void
    {
        $this->globalRule(rate: 1000);
        [$vendorOrder, $payment] = $this->paidVendorOrder(items: 10000, shipping: 500);
        $this->refundAllocation($vendorOrder, $payment, 2000, RefundStatus::Succeeded, RefundAllocationType::Item, processedAt: now()->subMinute());
        app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);

        $this->artisan('vendor-ledger:reconcile')->assertSuccessful();

        $this->assertSame(10500, VendorLedgerEntry::query()->where('type', VendorLedgerEntryType::Sale)->value('amount'));
        $this->assertSame(-2000, VendorLedgerEntry::query()->where('type', VendorLedgerEntryType::Refund)->value('amount'));
        $this->assertSame(-800, VendorLedgerEntry::query()->where('type', VendorLedgerEntryType::Commission)->value('amount'));
        $this->assertSame(7700, app(VendorEarningsService::class)->summaryFor($vendorOrder->vendor, 'USD', now()->addDays(10))->netEarnings);
    }

    public function test_balance_service_supports_pending_available_negative_and_currency_scoped_balances(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        VendorLedgerEntry::factory()->create(['vendor_id' => $vendor->id, 'currency' => 'USD', 'type' => VendorLedgerEntryType::Sale, 'amount' => 10000, 'available_at' => now()->addDay()]);
        VendorLedgerEntry::factory()->create(['vendor_id' => $vendor->id, 'currency' => 'USD', 'type' => VendorLedgerEntryType::Refund, 'amount' => -12000, 'available_at' => now()->subMinute()]);
        VendorLedgerEntry::factory()->create(['vendor_id' => $vendor->id, 'currency' => 'EUR', 'type' => VendorLedgerEntryType::Sale, 'amount' => 5000, 'available_at' => now()->subMinute()]);

        $usd = app(VendorEarningsService::class)->summaryFor($vendor, 'USD');
        $eur = app(VendorEarningsService::class)->summaryFor($vendor, 'EUR');

        $this->assertSame(-12000, $usd->availableBalance);
        $this->assertSame(10000, $usd->pendingBalance);
        $this->assertSame(-2000, $usd->netEarnings);
        $this->assertSame(5000, $eur->availableBalance);
    }

    public function test_vendor_and_admin_ledger_pages_are_authorized_and_scoped(): void
    {
        $this->globalRule();
        [$vendorOrder] = $this->paidVendorOrder();
        app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $otherVendor = Vendor::factory()->approved()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($vendorOrder->vendor->user)
            ->get(route('vendor.earnings.index'))
            ->assertOk()
            ->assertSeeText($vendorOrder->vendor_order_number);

        $this->actingAs($otherVendor->user)
            ->get(route('vendor.earnings.index'))
            ->assertOk()
            ->assertDontSeeText($vendorOrder->vendor_order_number);

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('vendor.earnings.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.vendors.financials', $vendorOrder->vendor))
            ->assertOk()
            ->assertSeeText($vendorOrder->vendor_order_number);

        $this->actingAs($admin)
            ->get(route('admin.vendor-ledger.index', ['reference' => $vendorOrder->vendor_order_number]))
            ->assertOk()
            ->assertSeeText($vendorOrder->vendor_order_number);
    }

    public function test_no_arbitrary_ledger_mutation_routes_exist(): void
    {
        $mutationRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'ledger'))
            ->filter(fn ($route) => collect($route->methods())->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])->isNotEmpty());

        $this->assertTrue($mutationRoutes->isEmpty());
    }

    public function test_database_seeder_posts_repeatable_demo_ledger_entries(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $demoOrderIds = \App\Models\Order::where('order_number', 'like', 'DEMO-REFUND-%')->pluck('id');
        $this->assertSame(9, VendorLedgerEntry::where(fn ($q) => $q->whereNotIn('order_id', $demoOrderIds)->orWhereNull('order_id'))->count());
        $this->assertSame(6, VendorLedgerEntry::whereIn('order_id', $demoOrderIds)->count());
        $this->assertSame(VendorLedgerEntry::query()->count(), VendorLedgerEntry::query()->distinct('source_key')->count('source_key'));
        $this->assertDatabaseHas('vendor_ledger_entries', ['type' => VendorLedgerEntryType::Sale->value]);
        $this->assertDatabaseHas('vendor_ledger_entries', ['type' => VendorLedgerEntryType::Commission->value]);
        $this->assertDatabaseHas('vendor_ledger_entries', ['type' => VendorLedgerEntryType::Refund->value]);
        $this->assertDatabaseHas('vendor_ledger_entries', ['type' => VendorLedgerEntryType::CommissionRefund->value]);
        $this->assertDatabaseHas('vendor_ledger_entries', ['type' => VendorLedgerEntryType::Payout->value]);
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

    private function paidVendorOrder(?Vendor $vendor = null, int $items = 10000, int $shipping = 500, VendorOrderStatus $status = VendorOrderStatus::Delivered, PaymentStatus $paymentStatus = PaymentStatus::Paid, PaymentMethod $method = PaymentMethod::Card, ?\DateTimeInterface $deliveredAt = null): array
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
            'delivered_at' => $status === VendorOrderStatus::Delivered ? ($deliveredAt ?? now()->subHour()) : null,
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
            'provider_reference' => $method === PaymentMethod::Card ? 'pi_ledger_'.$order->id : null,
            'paid_at' => in_array($paymentStatus, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true) ? now() : null,
        ]);

        return [$vendorOrder->load(['order.payment', 'vendor.user', 'items']), $payment, $order];
    }

    private function pendingRefund(VendorOrder $vendorOrder, Payment $payment, int $amount, ?\DateTimeInterface $processedAt = null): Refund
    {
        $refund = Refund::factory()->create([
            'order_id' => $vendorOrder->order_id,
            'payment_id' => $payment->id,
            'status' => RefundStatus::Pending,
            'amount' => $amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'processed_at' => $processedAt,
        ]);

        $refund->allocations()->create([
            'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $vendorOrder->items->first()->id,
            'type' => RefundAllocationType::Item,
            'amount' => $amount,
            'quantity' => 1,
        ]);

        return $refund;
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
            'provider_reference' => $status === RefundStatus::Succeeded ? 're_ledger_'.$vendorOrder->id.'_'.str()->random(6) : null,
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
