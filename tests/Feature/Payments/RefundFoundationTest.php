<?php

namespace Tests\Feature\Payments;

use App\Actions\Refunds\CreateRefund;
use App\Actions\Refunds\MarkRefundFailed;
use App\Actions\Refunds\MarkRefundSucceeded;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderCancellationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Enums\VendorOrderStatus;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Payments\CashOnDeliveryPaymentProvider;
use App\Services\Payments\PaymentInitializationResult;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentRefundResult;
use App\Services\Payments\RefundablePaymentProvider;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_stripe_item_and_shipping_refund_with_provider_derived_values(): void
    {
        [$order, $payment, $vendorOrders] = $this->paidStripeOrder();
        $admin = User::factory()->admin()->create();
        $fake = $this->fakeRefundProvider(PaymentRefundResult::succeeded('re_test_1'));

        $item = $vendorOrders[0]->items->first();
        $itemAmount = number_format($item->unit_price / 100, 2, '.', '');

        $this->actingAs($admin)
            ->post(route('admin.orders.refunds.store', $order), [
                'reason' => 'Damaged item',
                'provider' => 'manual',
                'currency' => 'EUR',
                'status' => RefundStatus::Succeeded->value,
                'allocations' => [
                    ['type' => 'item', 'order_item_id' => $item->id, 'quantity' => 1, 'amount' => $itemAmount],
                    ['type' => 'shipping', 'vendor_order_id' => $vendorOrders[0]->id, 'amount' => '5.00'],
                ],
            ])
            ->assertRedirect();

        $refund = Refund::query()->with('allocations')->firstOrFail();

        $this->assertSame('stripe', $refund->provider);
        $this->assertSame('USD', $refund->currency);
        $this->assertSame($item->unit_price + 500, $refund->amount);
        $this->assertSame(RefundStatus::Succeeded, $refund->status);
        $this->assertSame('re_test_1', $refund->provider_reference);
        $this->assertSame($refund->amount, $refund->allocations->sum('amount'));
        $this->assertSame($payment->provider_reference, $fake->paymentIntent);
        $this->assertSame($refund->amount, $fake->amount);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->refresh()->payment_status);
    }

    public function test_uncollected_payments_cannot_be_refunded(): void
    {
        [$order] = $this->paidStripeOrder(paymentStatus: PaymentStatus::Pending);

        $this->expectException(ValidationException::class);

        app(CreateRefund::class)->handle($order, User::factory()->admin()->create(), 'No money collected', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 100],
        ]);
    }

    public function test_refund_capacity_counts_pending_and_releases_failed_refunds(): void
    {
        [$order, $payment] = $this->paidStripeOrder(amount: 10000);
        $admin = User::factory()->admin()->create();

        $pending = app(CreateRefund::class)->handle($order, $admin, 'Pending provider refund', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 7000],
        ]);

        try {
            app(CreateRefund::class)->handle($order, $admin, 'Too much', null, [
                ['type' => RefundAllocationType::Adjustment->value, 'amount' => 4000],
            ]);
            $this->fail('Over-refund was accepted while a pending refund protected capacity.');
        } catch (ValidationException) {
            $this->assertSame(RefundStatus::Pending, $pending->refresh()->status);
        }

        app(MarkRefundFailed::class)->handle($pending, 'provider_down', 'Provider rejected refund.');

        $refund = app(CreateRefund::class)->handle($order, $admin, 'Capacity restored', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 10000],
        ]);

        $this->assertSame(10000, $refund->amount);
        $this->assertSame(10000, app(RefundFinancials::class)->summaryForPayment($payment->refresh())->pendingRefundAmount);
    }

    public function test_item_quantity_and_amount_capacity_are_enforced(): void
    {
        [$order, , $vendorOrders] = $this->paidStripeOrder();
        $admin = User::factory()->admin()->create();
        $item = $vendorOrders[0]->items->first();

        app(CreateRefund::class)->handle($order, $admin, 'One unit', null, [
            ['type' => RefundAllocationType::Item->value, 'order_item_id' => $item->id, 'quantity' => 1, 'amount' => $item->unit_price],
        ]);

        $this->expectException(ValidationException::class);

        app(CreateRefund::class)->handle($order, $admin, 'Too many units', null, [
            ['type' => RefundAllocationType::Item->value, 'order_item_id' => $item->id, 'quantity' => 2, 'amount' => $item->unit_price * 2],
        ]);
    }

    public function test_foreign_item_and_vendor_order_allocations_are_rejected(): void
    {
        [$order] = $this->paidStripeOrder();
        [$otherOrder, , $otherVendorOrders] = $this->paidStripeOrder();
        $admin = User::factory()->admin()->create();
        $foreignItem = $otherVendorOrders[0]->items->first();

        try {
            app(CreateRefund::class)->handle($order, $admin, 'Foreign item', null, [
                ['type' => RefundAllocationType::Item->value, 'order_item_id' => $foreignItem->id, 'amount' => 100],
            ]);
            $this->fail('Foreign item allocation was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('refunds', 0);
        }

        $this->expectException(ValidationException::class);

        app(CreateRefund::class)->handle($otherOrder, $admin, 'Foreign shipping', null, [
            ['type' => RefundAllocationType::Shipping->value, 'vendor_order_id' => $order->vendorOrders()->first()->id, 'amount' => 100],
        ]);
    }

    public function test_successful_full_refund_synchronizes_payment_without_mutating_totals_inventory_or_fulfillment(): void
    {
        [$order, $payment, $vendorOrders, $inventory] = $this->paidStripeOrder(status: OrderStatus::Completed, vendorStatus: VendorOrderStatus::Delivered);
        $original = [$order->grand_total, $vendorOrders[0]->grand_total, $vendorOrders[0]->items->first()->line_total, $payment->amount, $inventory->on_hand, $inventory->reserved];

        $refund = app(CreateRefund::class)->handle($order, User::factory()->admin()->create(), 'Full refund', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => $payment->amount],
        ]);

        app(MarkRefundSucceeded::class)->handle($refund, 're_full');

        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(VendorOrderStatus::Delivered, $vendorOrders[0]->refresh()->status);
        $this->assertSame($original, [$order->grand_total, $vendorOrders[0]->refresh()->grand_total, $vendorOrders[0]->items->first()->line_total, $payment->amount, $inventory->refresh()->on_hand, $inventory->reserved]);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_duplicate_success_and_stale_failure_do_not_downgrade_refund(): void
    {
        [$order, $payment] = $this->paidStripeOrder(amount: 10000);
        $refund = app(CreateRefund::class)->handle($order, User::factory()->admin()->create(), 'Partial refund', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 2500],
        ]);

        app(MarkRefundSucceeded::class)->handle($refund, 're_dup');
        app(MarkRefundSucceeded::class)->handle($refund, 're_dup');
        app(MarkRefundFailed::class)->handle($refund, 'stale', 'Out of order failure');

        $this->assertSame(RefundStatus::Succeeded, $refund->refresh()->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->refresh()->status);
        $this->assertSame(2500, app(RefundFinancials::class)->summaryForPayment($payment)->refundedAmount);
    }

    public function test_verified_refund_webhooks_are_idempotent_and_ignore_stale_failure(): void
    {
        [$order, $payment] = $this->paidStripeOrder(amount: 10000);
        $refund = app(CreateRefund::class)->handle($order, User::factory()->admin()->create(), 'Webhook refund', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 2500],
        ]);
        $refund->forceFill(['provider_reference' => 're_webhook_1'])->save();

        $this->postStripeRefundWebhook('evt_refund_success_1', 'refund.updated', [
            'id' => 're_webhook_1',
            'status' => 'succeeded',
            'metadata' => ['refund_id' => (string) $refund->id],
        ])->assertOk();

        $this->postStripeRefundWebhook('evt_refund_success_1', 'refund.updated', [
            'id' => 're_webhook_1',
            'status' => 'succeeded',
            'metadata' => ['refund_id' => (string) $refund->id],
        ])->assertOk();

        $this->postStripeRefundWebhook('evt_refund_failed_late', 'refund.failed', [
            'id' => 're_webhook_1',
            'status' => 'failed',
            'failure_reason' => 'expired_or_canceled_card',
        ])->assertOk();

        $this->assertSame(RefundStatus::Succeeded, $refund->refresh()->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->refresh()->status);
        $this->assertSame(2500, app(RefundFinancials::class)->summaryForPayment($payment)->refundedAmount);
        $this->assertDatabaseCount('payment_webhook_events', 2);
    }

    public function test_late_payment_review_is_resolved_by_successful_full_refund_and_order_stays_cancelled(): void
    {
        [$order, $payment] = $this->paidStripeOrder(status: OrderStatus::Cancelled, paymentStatus: PaymentStatus::Paid);
        $order->forceFill([
            'cancellation_source' => OrderCancellationSource::PaymentExpired,
            'cancellation_reason' => 'Online payment expired.',
            'cancelled_at' => now(),
        ])->save();
        $payment->forceFill([
            'requires_review_at' => now(),
            'review_reason' => 'Payment succeeded after order cancellation. Refund review is required.',
        ])->save();

        $refund = app(CreateRefund::class)->handle($order, User::factory()->admin()->create(), 'Late payment after cancellation', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => $payment->amount],
        ]);

        app(MarkRefundSucceeded::class)->handle($refund, 're_late');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertNotNull($payment->review_resolved_at);
    }

    public function test_customer_and_vendor_refund_visibility_is_scoped(): void
    {
        [$order, , $vendorOrders] = $this->paidStripeOrder();
        [$otherOrder] = $this->paidStripeOrder();
        $admin = User::factory()->admin()->create();
        $refund = app(CreateRefund::class)->handle($order, $admin, 'Seller refund', null, [
            ['type' => RefundAllocationType::Item->value, 'order_item_id' => $vendorOrders[0]->items->first()->id, 'quantity' => 1, 'amount' => $vendorOrders[0]->items->first()->unit_price],
        ]);
        app(MarkRefundSucceeded::class)->handle($refund, 're_visible');

        $this->actingAs($order->user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText($refund->refund_number)
            ->assertDontSeeText('re_visible');

        $this->actingAs($order->user)
            ->get(route('account.orders.show', $otherOrder))
            ->assertNotFound();

        $this->actingAs($vendorOrders[0]->vendor->user)
            ->get(route('vendor.orders.show', $vendorOrders[0]))
            ->assertOk()
            ->assertSeeText('Refund Attribution')
            ->assertSeeText('Refund attributed');

        $this->actingAs($vendorOrders[1]->vendor->user)
            ->get(route('vendor.orders.show', $vendorOrders[1]))
            ->assertOk()
            ->assertDontSeeText('Refund Attribution');
    }

    public function test_admin_refund_index_and_detail_render_with_filters(): void
    {
        [$order] = $this->paidStripeOrder();
        $admin = User::factory()->admin()->create();
        $refund = app(CreateRefund::class)->handle($order, $admin, 'Admin listing refund', 'Internal note only.', [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 1200],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.refunds.index', ['status' => RefundStatus::Pending->value, 'provider' => 'stripe', 'q' => $refund->refund_number]))
            ->assertOk()
            ->assertSeeText($refund->refund_number)
            ->assertSeeText($order->order_number);

        $this->actingAs($admin)
            ->get(route('admin.refunds.show', $refund))
            ->assertOk()
            ->assertSeeText('Admin listing refund')
            ->assertSeeText('Internal note only.')
            ->assertSeeText('Allocations');
    }

    public function test_non_admins_cannot_view_admin_refund_pages(): void
    {
        [$order] = $this->paidStripeOrder();
        $refund = app(CreateRefund::class)->handle($order, User::factory()->admin()->create(), 'Private refund', null, [
            ['type' => RefundAllocationType::Adjustment->value, 'amount' => 1200],
        ]);

        $this->actingAs($order->user)
            ->get(route('admin.refunds.show', $refund))
            ->assertForbidden();

        $this->actingAs($order->vendorOrders->first()->vendor->user)
            ->get(route('admin.refunds.index'))
            ->assertForbidden();
    }

    public function test_customer_and_vendor_cannot_create_refunds(): void
    {
        [$order, , $vendorOrders] = $this->paidStripeOrder();

        $payload = [
            'reason' => 'Not allowed',
            'allocations' => [
                ['type' => 'adjustment', 'amount' => '1.00'],
            ],
        ];

        $this->actingAs($order->user)
            ->post(route('admin.orders.refunds.store', $order), $payload)
            ->assertForbidden();

        $this->actingAs($vendorOrders[0]->vendor->user)
            ->post(route('admin.orders.refunds.store', $order), $payload)
            ->assertForbidden();
    }

    private function paidStripeOrder(int $amount = 20500, OrderStatus $status = OrderStatus::Processing, VendorOrderStatus $vendorStatus = VendorOrderStatus::Processing, PaymentStatus $paymentStatus = PaymentStatus::Paid): array
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id]);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'currency' => 'USD',
            'items_subtotal' => $amount - 500,
            'shipping_total' => 500,
            'grand_total' => $amount,
        ]);

        $shipping = min(500, max(0, $amount - 1000));
        $vendorBSubtotal = max(1000, intdiv($amount - $shipping, 4));
        $vendorASubtotal = $amount - $shipping - $vendorBSubtotal;
        if ($vendorASubtotal % 2 !== 0) {
            $vendorASubtotal--;
            $vendorBSubtotal++;
        }

        $vendorA = $this->vendorOrder($order, $vendorASubtotal, $shipping, $vendorStatus);
        $vendorB = $this->vendorOrder($order, $vendorBSubtotal, 0, $vendorStatus);
        $inventory = $this->item($vendorA, 2, intdiv($vendorASubtotal, 2));
        $this->item($vendorB, 1, $vendorBSubtotal);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Card,
            'provider' => 'stripe',
            'status' => $paymentStatus,
            'currency' => 'USD',
            'amount' => $amount,
            'provider_reference' => 'pi_refund_'.$order->id,
            'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
        ]);

        return [$order->load('user', 'vendorOrders.vendor.user', 'vendorOrders.items'), $payment, [$vendorA->load('items'), $vendorB->load('items')], $inventory];
    }

    private function vendorOrder(Order $order, int $itemsSubtotal, int $shipping, VendorOrderStatus $status): VendorOrder
    {
        $vendor = Vendor::factory()->approved()->create();
        $method = ShippingMethod::factory()->create(['vendor_id' => $vendor->id]);

        return VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'shipping_method_id' => $method->id,
            'status' => $status,
            'items_subtotal' => $itemsSubtotal,
            'shipping_total' => $shipping,
            'grand_total' => $itemsSubtotal + $shipping,
        ]);
    }

    private function item(VendorOrder $vendorOrder, int $quantity, int $unitPrice): Inventory
    {
        $product = Product::factory()->approved()->create(['vendor_id' => $vendorOrder->vendor_id]);
        $variant = ProductVariant::factory()->default()->create(['product_id' => $product->id, 'base_price' => $unitPrice]);
        $inventory = Inventory::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2],
        );
        $item = OrderItem::factory()->create([
            'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);
        InventoryReservation::factory()->create([
            'inventory_id' => $inventory->id,
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'status' => InventoryReservationStatus::Active,
        ]);

        return $inventory;
    }

    private function fakeRefundProvider(PaymentRefundResult $result): object
    {
        $fake = new class($result) implements PaymentProvider, RefundablePaymentProvider
        {
            public ?int $amount = null;

            public ?string $paymentIntent = null;

            public function __construct(private readonly PaymentRefundResult $result)
            {
            }

            public function code(): string
            {
                return 'stripe';
            }

            public function initialize(Payment $payment): PaymentInitializationResult
            {
                return PaymentInitializationResult::pending('Not used.');
            }

            public function refund(Refund $refund): PaymentRefundResult
            {
                $this->amount = $refund->amount;
                $this->paymentIntent = $refund->payment->provider_reference;

                return $this->result;
            }
        };

        $this->app->instance(PaymentProviderManager::class, new PaymentProviderManager([
            app(CashOnDeliveryPaymentProvider::class),
            $fake,
        ]));

        return $fake;
    }

    private function postStripeRefundWebhook(string $eventId, string $type, array $refundOverrides)
    {
        config(['payments.stripe.webhook_secret' => 'whsec_marketplace']);

        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2026-08-28.preview',
            'created' => time(),
            'type' => $type,
            'data' => [
                'object' => array_merge([
                    'id' => 're_test',
                    'object' => 'refund',
                    'amount' => 2500,
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test',
                    'status' => 'pending',
                    'metadata' => [],
                ], $refundOverrides),
            ],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_marketplace');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
