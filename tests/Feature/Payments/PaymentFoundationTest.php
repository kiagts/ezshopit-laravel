<?php

namespace Tests\Feature\Payments;

use App\Actions\Orders\Fulfillment\TransitionVendorOrderStatus;
use App\Actions\Payments\UpdatePaymentStatus;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\CashOnDeliveryPaymentProvider;
use App\Services\Checkout\CheckoutSession;
use App\Services\Payments\PaymentInitializationResult;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentProviderManager;
use App\Enums\RefundStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_auto_selects_cash_on_delivery_and_updates_payment_method(): void
    {
        [$customer] = $this->checkoutFixture();

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Payment Method')
            ->assertSeeText('Cash on Delivery')
            ->assertSeeText('Pay when your order is delivered.')
            ->assertSeeText('Place Order')
            ->assertSessionHas(CheckoutSession::PAYMENT_METHOD_KEY, PaymentMethod::CashOnDelivery->value);

        $this->actingAs($customer)
            ->put(route('checkout.payment.update'), ['payment_method' => PaymentMethod::CashOnDelivery->value])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas(CheckoutSession::PAYMENT_METHOD_KEY, PaymentMethod::CashOnDelivery->value);

        $this->actingAs($customer)
            ->from(route('checkout.index'))
            ->put(route('checkout.payment.update'), ['payment_method' => 'stripe'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_card_payment_method_is_available_only_when_stripe_is_configured(): void
    {
        [$customer] = $this->checkoutFixture();

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSeeText('Pay securely by debit or credit card.');

        $this->enableStripe();

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Card')
            ->assertSeeText('Pay securely by debit or credit card.');
    }

    public function test_cod_order_creates_one_pending_payment_and_one_pending_attempt(): void
    {
        [$customer, $variant] = $this->checkoutFixture(price: 12000, shipping: 500);
        $key = $this->readyCheckout($customer);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertRedirect();

        $order = Order::query()->with(['payment.attempts', 'vendorOrders.items.reservation'])->firstOrFail();
        $payment = $order->payment;
        $attempt = $payment->attempts->first();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(PaymentMethod::CashOnDelivery, $payment->method);
        $this->assertSame('cash_on_delivery', $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($order->grand_total, $payment->amount);
        $this->assertSame($order->currency, $payment->currency);
        $this->assertNull($payment->paid_at);
        $this->assertSame(1, $payment->attempts->count());
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(PaymentAttemptStatus::Pending, $attempt->status);
        $this->assertSame($payment->amount, $attempt->amount);
        $this->assertSame($payment->currency, $attempt->currency);
        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame(InventoryReservationStatus::Active, InventoryReservation::query()->first()->status);
        $this->assertSame(1, $variant->inventory->refresh()->reserved);
        $this->assertSame(10, $variant->inventory->on_hand);
        $this->assertNull(session(CheckoutSession::PAYMENT_METHOD_KEY));
    }

    public function test_card_checkout_creates_pending_order_then_initializes_stripe_after_commit(): void
    {
        [$order] = $this->placedStripeOrder();

        $payment = $order->payment;
        $attempt = $payment->attempts->first();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(PaymentMethod::Card, $payment->method);
        $this->assertSame('stripe', $payment->provider);
        $this->assertSame('pi_test_1', $payment->provider_reference);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->expires_at);
        $this->assertTrue($payment->expires_at->isFuture());
        $this->assertSame(1, $payment->attempts->count());
        $this->assertSame(PaymentAttemptStatus::Pending, $attempt->status);
        $this->assertSame('pi_test_1', $attempt->provider_reference);
        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame(InventoryReservationStatus::Active, InventoryReservation::query()->first()->status);
    }

    public function test_cod_has_no_expiry_and_card_retry_does_not_extend_original_expiry(): void
    {
        [$customer] = $this->checkoutFixture();
        $key = $this->readyCheckout($customer);

        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();
        $this->assertNull(Order::query()->firstOrFail()->payment->expires_at);

        [$order] = $this->placedStripeOrder();
        $originalExpiry = now()->addMinutes(12)->startOfSecond();
        $order->payment->forceFill(['expires_at' => $originalExpiry])->save();
        $order->payment->attempts()->first()->forceFill([
            'status' => PaymentAttemptStatus::Failed,
            'completed_at' => now(),
        ])->save();

        $this->actingAs($order->user)
            ->postJson(route('account.orders.payment.retry', $order))
            ->assertOk();

        $this->assertTrue($order->payment->refresh()->expires_at->equalTo($originalExpiry));
    }

    public function test_payment_method_disabled_between_checkout_and_submit_rolls_back_order(): void
    {
        [$customer, $variant] = $this->checkoutFixture();
        $key = $this->readyCheckout($customer);

        config(['payments.methods.cash_on_delivery.enabled' => false]);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, PaymentAttempt::query()->count());
        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(0, $variant->inventory->refresh()->reserved);
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_idempotency_does_not_duplicate_payment_or_attempt(): void
    {
        [$customer, $variant] = $this->checkoutFixture();
        $key = $this->readyCheckout($customer);

        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, PaymentAttempt::query()->count());
        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame(1, $variant->inventory->refresh()->reserved);
    }

    public function test_payment_status_transition_synchronizes_order_without_mutating_inventory_or_fulfillment(): void
    {
        [$customer, $variant] = $this->checkoutFixture();
        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $order = Order::query()->with('vendorOrders')->firstOrFail();
        $payment = $order->payment()->firstOrFail();
        $vendorOrderIds = $order->vendorOrders->pluck('id')->all();

        app(UpdatePaymentStatus::class)->handle($payment, PaymentStatus::Paid);

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertTrue($order->vendorOrders()->whereIn('id', $vendorOrderIds)->get()->every(fn ($vendorOrder): bool => $vendorOrder->status === VendorOrderStatus::Pending));
        $this->assertSame(1, $variant->inventory->refresh()->reserved);
        $this->assertSame(10, $variant->inventory->on_hand);

        $this->expectException(ValidationException::class);
        app(UpdatePaymentStatus::class)->handle($payment->refresh(), PaymentStatus::Pending);
    }

    public function test_stripe_success_webhook_marks_payment_paid_without_mutating_order_or_reservation(): void
    {
        [$order, $variant] = $this->placedStripeOrder();

        $this->postStripeWebhook('evt_success_1', 'payment_intent.succeeded', [
            'id' => 'pi_test_1',
            'amount' => $order->grand_total,
            'currency' => strtolower($order->currency),
            'status' => 'succeeded',
        ])->assertOk();

        $payment = $order->payment->refresh();
        $attempt = $payment->attempts()->firstOrFail();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(VendorOrderStatus::Pending, $order->vendorOrders()->first()->status);
        $this->assertSame(InventoryReservationStatus::Active, InventoryReservation::query()->first()->status);
        $this->assertSame(1, $variant->inventory->refresh()->reserved);
        $this->assertSame(PaymentAttemptStatus::Succeeded, $attempt->status);

        $this->postStripeWebhook('evt_success_1', 'payment_intent.succeeded', [
            'id' => 'pi_test_1',
            'amount' => $order->grand_total,
            'currency' => strtolower($order->currency),
            'status' => 'succeeded',
        ])->assertOk();

        $this->assertSame(1, $payment->webhookEvents()->count());
    }

    public function test_stripe_webhook_amount_mismatch_and_unknown_intents_do_not_mark_paid(): void
    {
        [$order] = $this->placedStripeOrder();

        $this->postStripeWebhook('evt_mismatch_1', 'payment_intent.succeeded', [
            'id' => 'pi_test_1',
            'amount' => $order->grand_total + 1,
            'currency' => strtolower($order->currency),
            'status' => 'succeeded',
        ])->assertOk();

        $this->postStripeWebhook('evt_unknown_1', 'payment_intent.succeeded', [
            'id' => 'pi_unknown',
            'amount' => $order->grand_total,
            'currency' => strtolower($order->currency),
            'status' => 'succeeded',
        ])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_stripe_failure_after_paid_does_not_downgrade_payment(): void
    {
        [$order] = $this->placedStripeOrder();

        $this->postStripeWebhook('evt_success_before_failure', 'payment_intent.succeeded', [
            'id' => 'pi_test_1',
            'amount' => $order->grand_total,
            'currency' => strtolower($order->currency),
            'status' => 'succeeded',
        ])->assertOk();

        $this->postStripeWebhook('evt_failure_after_paid', 'payment_intent.payment_failed', [
            'id' => 'pi_test_1',
            'amount' => $order->grand_total,
            'currency' => strtolower($order->currency),
            'status' => 'requires_payment_method',
            'last_payment_error' => ['code' => 'card_declined', 'message' => 'Declined'],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    public function test_invalid_stripe_webhook_signature_is_rejected(): void
    {
        $this->enableStripe();

        $payload = json_encode($this->stripeEventPayload('evt_bad_sig', 'payment_intent.succeeded', ['id' => 'pi_test_1']));

        $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad'], $payload)
            ->assertBadRequest();
    }

    public function test_unpaid_card_order_blocks_fulfillment_but_paid_card_and_cod_can_continue(): void
    {
        [$cardOrder] = $this->placedStripeOrder();
        $cardVendorOrder = $cardOrder->vendorOrders()->firstOrFail();

        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($cardVendorOrder, VendorOrderStatus::Confirmed, $cardVendorOrder->vendor->user));

        $this->postStripeWebhook('evt_fulfillment_paid', 'payment_intent.succeeded', [
            'id' => 'pi_test_1',
            'amount' => $cardOrder->grand_total,
            'currency' => strtolower($cardOrder->currency),
            'status' => 'succeeded',
        ])->assertOk();

        app(TransitionVendorOrderStatus::class)->handle($cardVendorOrder, VendorOrderStatus::Confirmed, $cardVendorOrder->vendor->user);
        $this->assertSame(VendorOrderStatus::Confirmed, $cardVendorOrder->refresh()->status);

        [$customer] = $this->checkoutFixture();
        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();
        $codOrder = Order::query()->whereHas('payment', fn ($query) => $query->where('method', PaymentMethod::CashOnDelivery))->latest()->firstOrFail();
        $codVendorOrder = $codOrder->vendorOrders()->firstOrFail();

        app(TransitionVendorOrderStatus::class)->handle($codVendorOrder, VendorOrderStatus::Confirmed, $codVendorOrder->vendor->user);
        $this->assertSame(VendorOrderStatus::Confirmed, $codVendorOrder->refresh()->status);
    }

    public function test_customer_can_retry_failed_card_payment_without_creating_new_order_or_reservation(): void
    {
        [$order] = $this->placedStripeOrder();
        $attempt = $order->payment->attempts()->firstOrFail();
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Failed,
            'failure_code' => 'card_declined',
            'failure_message' => 'Declined',
            'completed_at' => now(),
        ])->save();

        $this->actingAs($order->user)
            ->postJson(route('account.orders.payment.retry', $order))
            ->assertOk()
            ->assertJsonPath('client_secret', 'secret_pi_test_2');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(2, PaymentAttempt::query()->count());
        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame('pi_test_2', $order->payment->refresh()->provider_reference);
    }

    public function test_expired_card_retry_is_blocked_without_new_attempt(): void
    {
        [$order] = $this->placedStripeOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($order->user)
            ->postJson(route('account.orders.payment.retry', $order))
            ->assertUnprocessable();

        $this->assertSame(1, PaymentAttempt::query()->count());
    }

    public function test_expiry_command_cancels_expired_card_order_and_releases_inventory_without_movement(): void
    {
        [$order, $variant] = $this->placedStripeOrder();
        $totals = [$order->items_subtotal, $order->shipping_total, $order->grand_total, $order->payment->amount];
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('orders:expire-unpaid')
            ->expectsOutput('Expired unpaid orders: 1')
            ->expectsOutput('Skipped: 0')
            ->expectsOutput('Failed: 0')
            ->assertExitCode(0);

        $order->refresh();
        $payment = $order->payment->refresh();
        $vendorOrder = $order->vendorOrders()->firstOrFail();
        $reservation = InventoryReservation::query()->firstOrFail();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->payment_status);
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertSame(VendorOrderStatus::Cancelled, $vendorOrder->status);
        $this->assertSame('payment_expired', $order->cancellation_source->value);
        $this->assertSame(InventoryReservationStatus::Released, $reservation->status);
        $this->assertSame(0, $variant->inventory->refresh()->reserved);
        $this->assertSame(10, $variant->inventory->on_hand);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame($totals, [$order->items_subtotal, $order->shipping_total, $order->grand_total, $payment->amount]);

        $this->artisan('orders:expire-unpaid')
            ->expectsOutput('Expired unpaid orders: 0')
            ->expectsOutput('Skipped: 0')
            ->expectsOutput('Failed: 0')
            ->assertExitCode(0);

        $this->assertSame(0, $variant->inventory->refresh()->reserved);
    }

    public function test_expiry_command_skips_paid_processing_cod_before_expiry_and_shipped_orders(): void
    {
        [$beforeExpiry] = $this->placedStripeOrder();
        $beforeExpiry->payment->forceFill(['expires_at' => now()->addMinute()])->save();

        [$paid] = $this->placedStripeOrder();
        $paid->payment->forceFill(['status' => PaymentStatus::Paid, 'expires_at' => now()->subMinute()])->save();
        $paid->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        [$processing] = $this->placedStripeOrder();
        $processing->payment->forceFill(['status' => PaymentStatus::Processing, 'expires_at' => now()->subMinute()])->save();

        [$shipped] = $this->placedStripeOrder();
        $shipped->payment->forceFill(['expires_at' => now()->subMinute()])->save();
        $shipped->vendorOrders()->first()->forceFill(['status' => VendorOrderStatus::Shipped, 'shipped_at' => now()])->save();

        [$customer] = $this->checkoutFixture();
        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $this->artisan('orders:expire-unpaid')
            ->expectsOutput('Expired unpaid orders: 0')
            ->expectsOutput('Failed: 0')
            ->assertExitCode(0);

        $this->assertSame(0, Order::query()->where('status', OrderStatus::Cancelled)->count());
    }

    public function test_multi_vendor_card_expiry_cancels_all_seller_orders_and_releases_all_reservations(): void
    {
        [$customer] = $this->multiVendorCheckoutFixture();
        $this->enableStripe();
        $this->fakeStripeProvider();
        $this->readyCheckout($customer);

        $this->actingAs($customer)
            ->put(route('checkout.payment.update'), ['payment_method' => PaymentMethod::Card->value])
            ->assertRedirect(route('checkout.index'));

        $key = $this->readyCheckout($customer);

        $this->actingAs($customer)
            ->postJson(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertOk();

        $order = Order::query()->with(['payment', 'vendorOrders.items.reservation'])->firstOrFail();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertSame(2, $order->vendorOrders()->count());
        $this->assertSame(2, InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->count());

        $this->artisan('orders:expire-unpaid')
            ->expectsOutput('Expired unpaid orders: 1')
            ->expectsOutput('Failed: 0')
            ->assertExitCode(0);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(2, $order->vendorOrders()->where('status', VendorOrderStatus::Cancelled)->count());
        $this->assertSame(2, InventoryReservation::query()->where('status', InventoryReservationStatus::Released)->count());
        $this->assertSame(0, Inventory::query()->sum('reserved'));
    }

    public function test_corrupt_expired_order_does_not_partially_cancel_or_release(): void
    {
        [$order, $variant] = $this->placedStripeOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();
        Inventory::query()->whereKey($variant->inventory->id)->update(['reserved' => 0]);

        $this->artisan('orders:expire-unpaid');

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment->refresh()->status);
        $this->assertSame(VendorOrderStatus::Pending, $order->vendorOrders()->first()->status);
        $this->assertSame(InventoryReservationStatus::Active, InventoryReservation::query()->first()->status);
        $this->assertSame(0, $variant->inventory->refresh()->reserved);
    }

    public function test_customer_can_cancel_own_unpaid_card_order_once(): void
    {
        [$order, $variant] = $this->placedStripeOrder();

        $this->actingAs($order->user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Cancel Unpaid Order');

        $this->actingAs($order->user)
            ->post(route('account.orders.cancel', $order), [
                'reason' => 'Ordered by mistake',
                'cancellation_source' => 'admin',
            ])
            ->assertRedirect(route('account.orders.show', $order));

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('customer', $order->cancellation_source->value);
        $this->assertSame('Ordered by mistake', $order->cancellation_reason);
        $this->assertSame(0, $variant->inventory->refresh()->reserved);

        $this->actingAs($order->user)
            ->post(route('account.orders.cancel', $order))
            ->assertRedirect(route('account.orders.show', $order));

        $this->assertSame(0, $variant->inventory->refresh()->reserved);
    }

    public function test_customer_cancel_route_blocks_guest_vendor_cross_customer_paid_cod_and_shipped_orders(): void
    {
        [$order] = $this->placedStripeOrder();
        $otherCustomer = User::factory()->customer()->create();
        $vendorUser = $order->vendorOrders()->first()->vendor->user;

        auth()->logout();
        $this->post(route('account.orders.cancel', $order))->assertRedirect(route('login'));

        $this->actingAs($vendorUser)->post(route('account.orders.cancel', $order))->assertForbidden();
        $this->actingAs($otherCustomer)->post(route('account.orders.cancel', $order))->assertNotFound();

        $this->actingAs($order->user)->post(route('account.orders.cancel', $order))->assertRedirect();

        [$paid] = $this->placedStripeOrder();
        $paid->payment->forceFill(['status' => PaymentStatus::Paid])->save();
        $this->actingAs($paid->user)->post(route('account.orders.cancel', $paid))->assertSessionHasErrors('order');

        [$codCustomer] = $this->checkoutFixture();
        $key = $this->readyCheckout($codCustomer);
        $this->actingAs($codCustomer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();
        $cod = Order::query()->whereHas('payment', fn ($query) => $query->where('method', PaymentMethod::CashOnDelivery))->firstOrFail();
        $this->actingAs($codCustomer)->post(route('account.orders.cancel', $cod))->assertSessionHasErrors('order');

        [$shipped] = $this->placedStripeOrder();
        $shipped->vendorOrders()->first()->forceFill(['status' => VendorOrderStatus::Shipped])->save();
        $this->actingAs($shipped->user)->post(route('account.orders.cancel', $shipped))->assertSessionHasErrors('order');
    }

    public function test_late_stripe_success_after_cancellation_flags_review_without_reactivating_inventory(): void
    {
        [$order, $variant] = $this->placedStripeOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->artisan('orders:expire-unpaid')->assertExitCode(0);

        $this->postStripeWebhook('evt_late_success', 'payment_intent.succeeded', [
            'id' => 'pi_test_1',
            'amount' => $order->grand_total,
            'currency' => strtolower($order->currency),
            'status' => 'succeeded',
        ])->assertOk();

        $payment = $order->payment->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(VendorOrderStatus::Cancelled, $order->vendorOrders()->first()->status);
        $this->assertSame(InventoryReservationStatus::Released, InventoryReservation::query()->first()->status);
        $this->assertSame(0, $variant->inventory->refresh()->reserved);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->requires_review_at);
        $this->assertSame('Payment succeeded after order cancellation. Refund review is required.', $payment->review_reason);
    }

    public function test_payment_amount_remains_immutable_after_catalog_shipping_and_address_changes(): void
    {
        [$customer, $variant, $method, $address] = $this->checkoutFixture(price: 9000, shipping: 600);
        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $payment = Payment::query()->firstOrFail();
        $variant->update(['base_price' => 50000]);
        $method->update(['flat_rate' => 9900]);
        $address->update(['first_name' => 'Changed']);

        $this->assertSame(9600, $payment->refresh()->amount);
        $this->assertSame('USD', $payment->currency);
    }

    public function test_customer_vendor_and_admin_payment_visibility_boundaries(): void
    {
        [$customer] = $this->multiVendorCheckoutFixture();
        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $order = Order::query()->with(['payment', 'vendorOrders.vendor'])->firstOrFail();
        $vendorAOrder = $order->vendorOrders->first();
        $vendorBOrder = $order->vendorOrders->last();
        $admin = User::factory()->admin()->create();
        $otherCustomer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Cash on Delivery')
            ->assertSeeText('Payment')
            ->assertSeeText('USD 303.00');

        $this->actingAs($otherCustomer)
            ->get(route('account.orders.show', $order))
            ->assertNotFound();

        $this->actingAs($vendorAOrder->vendor->user)
            ->get(route('vendor.orders.show', $vendorAOrder))
            ->assertOk()
            ->assertSeeText('Cash on Delivery')
            ->assertSeeText('Pending')
            ->assertSeeText('USD 101.00')
            ->assertDontSeeText('USD 303.00')
            ->assertDontSeeText($vendorBOrder->vendor_order_number);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSeeText($order->order_number)
            ->assertSeeText('USD 303.00');

        $this->actingAs($admin)
            ->get(route('admin.payments.show', $order->payment))
            ->assertOk()
            ->assertSeeText('Attempts')
            ->assertSeeText('Cash on Delivery')
            ->assertSeeText('#1');
    }

    public function test_database_seeder_creates_repeatable_cod_and_card_demo_payments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(16, Payment::query()->count());
        $this->assertSame(0, PaymentAttempt::query()->count());
        $this->assertSame(7, Payment::query()->where('method', PaymentMethod::CashOnDelivery)->where('status', PaymentStatus::Pending)->count());
        $this->assertSame(1, Payment::query()->where('method', PaymentMethod::Card)->where('status', PaymentStatus::Pending)->whereNotNull('expires_at')->count());
        $this->assertSame(2, Payment::query()->where('method', PaymentMethod::Card)->where('status', PaymentStatus::Cancelled)->whereNotNull('expires_at')->count());
        $this->assertSame(2, Payment::query()->where('method', PaymentMethod::Card)->where('status', PaymentStatus::Paid)->count());
        $this->assertSame(2, Payment::query()->where('method', PaymentMethod::Card)->where('status', PaymentStatus::PartiallyRefunded)->count());
        $this->assertSame(2, Payment::query()->where('method', PaymentMethod::Card)->where('status', PaymentStatus::Refunded)->count());
        $this->assertSame(6, Refund::query()->count());
        $this->assertSame(4, Refund::query()->where('status', RefundStatus::Succeeded)->count());
        $this->assertSame(1, Refund::query()->where('status', RefundStatus::Pending)->count());
        $this->assertSame(1, Refund::query()->where('status', RefundStatus::Failed)->count());
        $this->assertDatabaseCount('vendor_order_commissions', 3);
        $this->assertDatabaseHas('refunds', ['refund_number' => 'REF-DEMO-LIFE-COMMISSION-ADJUSTED']);
        $this->assertDatabaseHas('commission_adjustments', ['commissionable_amount_delta' => -1500, 'commission_amount_delta' => -120]);
        $this->assertDatabaseHas('orders', ['order_number' => 'DEMO-LIFE-CARD-EXPIRED', 'cancellation_source' => 'payment_expired']);
        $this->assertDatabaseHas('orders', ['order_number' => 'DEMO-LIFE-CARD-CUSTOMER-CANCELLED', 'cancellation_source' => 'customer']);
        $this->assertDatabaseHas('orders', ['order_number' => 'DEMO-LIFE-LATE-REFUNDED', 'payment_status' => PaymentStatus::Refunded->value]);
    }

    private function expectValidationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure was not thrown.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function checkoutFixture(int $price = 10000, int $shipping = 500): array
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'Payment Vendor']);
        $variant = $this->variantFor($vendor, 'Payment Product', 'PAYMENT-SKU-'.fake()->unique()->numberBetween(1000, 9999), $price);
        $method = ShippingMethod::factory()->restricted(['PK'])->create(['vendor_id' => $vendor->id, 'name' => 'COD Delivery', 'code' => 'cod_delivery', 'flat_rate' => $shipping]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        return [$customer, $variant, $method, $address];
    }

    private function placedStripeOrder(): array
    {
        [$customer, $variant] = $this->checkoutFixture();
        $this->enableStripe();
        $this->fakeStripeProvider();
        $this->readyCheckout($customer);

        $this->actingAs($customer)
            ->put(route('checkout.payment.update'), ['payment_method' => PaymentMethod::Card->value])
            ->assertRedirect(route('checkout.index'));

        $key = $this->readyCheckout($customer);

        $this->actingAs($customer)
            ->postJson(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertOk()
            ->assertJsonPath('client_secret', 'secret_pi_test_1');

        return [Order::query()->with(['user', 'payment.attempts', 'vendorOrders.vendor.user', 'vendorOrders.items.reservation'])->latest('id')->firstOrFail(), $variant];
    }

    private function enableStripe(): void
    {
        config([
            'payments.stripe.enabled' => true,
            'payments.stripe.public_key' => 'pk_test_marketplace',
            'payments.stripe.secret_key' => 'sk_test_marketplace',
            'payments.stripe.webhook_secret' => 'whsec_marketplace',
            'payments.methods.card.enabled' => true,
        ]);
    }

    private function fakeStripeProvider(): void
    {
        $this->app->instance(PaymentProviderManager::class, new PaymentProviderManager([
            app(CashOnDeliveryPaymentProvider::class),
            new class implements PaymentProvider
            {
                public function code(): string
                {
                    return 'stripe';
                }

                public function initialize(Payment $payment): PaymentInitializationResult
                {
                    $attempt = $payment->attempts()
                        ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
                        ->latest('attempt_number')
                        ->first();

                    if (! $attempt) {
                        $attempt = $payment->attempts()->create([
                            'attempt_number' => ((int) $payment->attempts()->max('attempt_number')) + 1,
                            'status' => PaymentAttemptStatus::Pending,
                            'amount' => $payment->amount,
                            'currency' => $payment->currency,
                            'started_at' => now(),
                        ]);
                    }

                    $reference = 'pi_test_'.$attempt->attempt_number;

                    $payment->forceFill([
                        'provider_reference' => $reference,
                        'status' => PaymentStatus::Pending,
                    ])->save();

                    $attempt->forceFill([
                        'provider_reference' => $reference,
                        'status' => PaymentAttemptStatus::Pending,
                        'failure_code' => null,
                        'failure_message' => null,
                        'completed_at' => null,
                    ])->save();

                    return PaymentInitializationResult::requiresClientConfirmation('secret_'.$reference, $reference);
                }
            },
        ]));
    }

    private function postStripeWebhook(string $eventId, string $type, array $intentOverrides)
    {
        $this->enableStripe();

        $payload = json_encode($this->stripeEventPayload($eventId, $type, $intentOverrides));
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_marketplace');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function stripeEventPayload(string $eventId, string $type, array $intentOverrides): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2026-08-28.preview',
            'created' => time(),
            'type' => $type,
            'data' => [
                'object' => array_merge([
                    'id' => 'pi_test_1',
                    'object' => 'payment_intent',
                    'amount' => 10500,
                    'currency' => 'usd',
                    'status' => 'requires_payment_method',
                ], $intentOverrides),
            ],
        ];
    }

    private function multiVendorCheckoutFixture(): array
    {
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendorA = Vendor::factory()->approved()->create(['display_name' => 'Vendor A']);
        $vendorB = Vendor::factory()->approved()->create(['display_name' => 'Vendor B']);
        $variantA = $this->variantFor($vendorA, 'Vendor A Product', 'VENDOR-A-PAY', 10000);
        $variantB = $this->variantFor($vendorB, 'Vendor B Product', 'VENDOR-B-PAY', 20000);
        ShippingMethod::factory()->restricted(['PK'])->create(['vendor_id' => $vendorA->id, 'flat_rate' => 100]);
        ShippingMethod::factory()->restricted(['PK'])->create(['vendor_id' => $vendorB->id, 'flat_rate' => 200]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variantA->id, 'quantity' => 1]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variantB->id, 'quantity' => 1]);

        return [$customer];
    }

    private function readyCheckout(User $customer): string
    {
        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Place Order');

        return (string) session(CheckoutSession::IDEMPOTENCY_KEY);
    }

    private function variantFor(Vendor $vendor, string $name, string $sku, int $price): ProductVariant
    {
        $product = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => Category::factory()->create()->id,
            'name' => $name,
        ]);
        $variant = ProductVariant::factory()->default()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'base_price' => $price,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2],
        );

        return $variant->load('inventory');
    }
}
