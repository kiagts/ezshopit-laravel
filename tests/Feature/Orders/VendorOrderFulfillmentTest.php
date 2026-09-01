<?php

namespace Tests\Feature\Orders;

use App\Actions\Orders\Fulfillment\RecalculateOrderStatus;
use App\Actions\Orders\Fulfillment\TransitionVendorOrderStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_lifecycle_commits_inventory_once_and_leaves_cod_pending(): void
    {
        [$vendorOrder, $inventory, $payment] = $this->placedVendorOrder(VendorOrderStatus::Pending, quantity: 2, onHand: 10);

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Confirmed, $vendorOrder->vendor->user);
        $this->assertSame(VendorOrderStatus::Confirmed, $vendorOrder->refresh()->status);
        $this->assertNotNull($vendorOrder->confirmed_at);
        $this->assertSame(10, $inventory->refresh()->on_hand);
        $this->assertSame(2, $inventory->reserved);
        $this->assertSame(InventoryReservationStatus::Active, $vendorOrder->items()->first()->reservation->status);

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Processing, $vendorOrder->vendor->user);
        $this->assertSame(VendorOrderStatus::Processing, $vendorOrder->refresh()->status);
        $this->assertNotNull($vendorOrder->processing_at);
        $this->assertSame(10, $inventory->refresh()->on_hand);
        $this->assertSame(2, $inventory->reserved);

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::ReadyToShip, $vendorOrder->vendor->user);
        $this->assertSame(VendorOrderStatus::ReadyToShip, $vendorOrder->refresh()->status);
        $this->assertNotNull($vendorOrder->ready_to_ship_at);
        $this->assertSame(10, $inventory->refresh()->on_hand);
        $this->assertSame(2, $inventory->reserved);

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Shipped, $vendorOrder->vendor->user);
        $movement = InventoryMovement::query()->firstOrFail();

        $this->assertSame(VendorOrderStatus::Shipped, $vendorOrder->refresh()->status);
        $this->assertNotNull($vendorOrder->shipped_at);
        $this->assertSame(8, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame(8, $inventory->availableQuantity());
        $this->assertSame(InventoryReservationStatus::Committed, $vendorOrder->items()->first()->reservation->refresh()->status);
        $this->assertSame(InventoryMovementType::OrderFulfillment, $movement->type);
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(10, $movement->before_quantity);
        $this->assertSame(8, $movement->after_quantity);
        $this->assertSame($vendorOrder->vendor->user_id, $movement->created_by);
        $this->assertSame(VendorOrder::class, $movement->reference_type);
        $this->assertSame($vendorOrder->id, $movement->reference_id);

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Shipped, $vendorOrder->vendor->user);
        $this->assertSame(8, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame(1, InventoryMovement::query()->count());

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Delivered, $vendorOrder->vendor->user);
        $this->assertSame(VendorOrderStatus::Delivered, $vendorOrder->refresh()->status);
        $this->assertNotNull($vendorOrder->delivered_at);
        $this->assertSame(8, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(OrderStatus::Completed, $vendorOrder->order->refresh()->status);
        $this->assertNotNull($vendorOrder->order->completed_at);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(PaymentMethod::CashOnDelivery, $payment->method);
    }

    public function test_invalid_lifecycle_transitions_are_rejected(): void
    {
        [$pending] = $this->placedVendorOrder(VendorOrderStatus::Pending);
        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($pending, VendorOrderStatus::Shipped));
        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($pending, VendorOrderStatus::Delivered));

        [$delivered] = $this->placedVendorOrder(VendorOrderStatus::Delivered, reservationStatus: InventoryReservationStatus::Committed, reserved: 0);
        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($delivered, VendorOrderStatus::Processing));

        [$cancelled] = $this->placedVendorOrder(VendorOrderStatus::Cancelled, reservationStatus: InventoryReservationStatus::Released, reserved: 0);
        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($cancelled, VendorOrderStatus::Confirmed));

        [$shipped] = $this->placedVendorOrder(VendorOrderStatus::Shipped, reservationStatus: InventoryReservationStatus::Committed, reserved: 0);
        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($shipped, VendorOrderStatus::Cancelled, reason: 'Too late'));
    }

    public function test_cancellation_releases_active_reservation_without_movement_or_total_mutation(): void
    {
        [$vendorOrder, $inventory, $payment] = $this->placedVendorOrder(VendorOrderStatus::ReadyToShip, quantity: 3, onHand: 12);
        $originalPaymentAmount = $payment->amount;
        $originalOrderTotal = $vendorOrder->order->grand_total;
        $originalVendorTotal = $vendorOrder->grand_total;

        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Cancelled, $vendorOrder->vendor->user));

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Cancelled, $vendorOrder->vendor->user, 'Inventory discrepancy');

        $this->assertSame(VendorOrderStatus::Cancelled, $vendorOrder->refresh()->status);
        $this->assertSame('Inventory discrepancy', $vendorOrder->cancellation_reason);
        $this->assertSame($vendorOrder->vendor->user_id, $vendorOrder->cancelled_by);
        $this->assertSame(InventoryReservationStatus::Released, $vendorOrder->items()->first()->reservation->refresh()->status);
        $this->assertSame(12, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame($originalPaymentAmount, $payment->refresh()->amount);
        $this->assertSame($originalOrderTotal, $vendorOrder->order->refresh()->grand_total);
        $this->assertSame($originalVendorTotal, $vendorOrder->refresh()->grand_total);

        app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Cancelled, $vendorOrder->vendor->user, 'Second click');
        $this->assertSame(12, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_multi_item_shipment_is_atomic_when_one_inventory_state_is_invalid(): void
    {
        [$vendorOrder, $firstInventory] = $this->placedVendorOrder(VendorOrderStatus::ReadyToShip, quantity: 1, onHand: 5);
        $secondInventory = $this->addItem($vendorOrder, quantity: 2, onHand: 1, reserved: 2);

        $this->expectValidationFailure(fn () => app(TransitionVendorOrderStatus::class)->handle($vendorOrder, VendorOrderStatus::Shipped, $vendorOrder->vendor->user));

        $this->assertSame(VendorOrderStatus::ReadyToShip, $vendorOrder->refresh()->status);
        $this->assertSame(5, $firstInventory->refresh()->on_hand);
        $this->assertSame(1, $firstInventory->reserved);
        $this->assertSame(1, $secondInventory->refresh()->on_hand);
        $this->assertSame(2, $secondInventory->reserved);
        $this->assertTrue($vendorOrder->items()->with('reservation')->get()->every(fn (OrderItem $item): bool => $item->reservation->status === InventoryReservationStatus::Active));
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_parent_order_status_aggregates_independent_vendor_orders(): void
    {
        [$first] = $this->placedVendorOrder(VendorOrderStatus::Pending);
        $order = $first->order;
        $second = $this->addVendorOrderTo($order, VendorOrderStatus::Pending);

        app(RecalculateOrderStatus::class)->handle($order);
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);

        $first->forceFill(['status' => VendorOrderStatus::Confirmed])->save();
        $second->forceFill(['status' => VendorOrderStatus::Confirmed])->save();
        app(RecalculateOrderStatus::class)->handle($order);
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);

        $second->forceFill(['status' => VendorOrderStatus::Processing])->save();
        app(RecalculateOrderStatus::class)->handle($order);
        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);

        $first->forceFill(['status' => VendorOrderStatus::Delivered])->save();
        app(RecalculateOrderStatus::class)->handle($order);
        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);

        $second->forceFill(['status' => VendorOrderStatus::Cancelled])->save();
        app(RecalculateOrderStatus::class)->handle($order);
        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);
        $this->assertNotNull($order->completed_at);

        $first->forceFill(['status' => VendorOrderStatus::Cancelled])->save();
        app(RecalculateOrderStatus::class)->handle($order);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_vendor_routes_enforce_ownership_and_role_boundaries(): void
    {
        [$vendorOrder] = $this->placedVendorOrder(VendorOrderStatus::Pending);
        [$otherVendorOrder] = $this->placedVendorOrder(VendorOrderStatus::Pending);
        $customer = User::factory()->customer()->create();

        $this->actingAs($vendorOrder->vendor->user)
            ->post(route('vendor.orders.confirm', $vendorOrder))
            ->assertRedirect();
        $this->assertSame(VendorOrderStatus::Confirmed, $vendorOrder->refresh()->status);

        $this->actingAs($vendorOrder->vendor->user)
            ->post(route('vendor.orders.processing', $otherVendorOrder))
            ->assertNotFound();

        $this->actingAs($customer)
            ->post(route('vendor.orders.processing', $vendorOrder))
            ->assertForbidden();

        auth()->logout();

        $this->post(route('vendor.orders.processing', $vendorOrder))
            ->assertRedirect(route('login'));
    }

    public function test_views_preserve_vendor_and_customer_privacy(): void
    {
        [$vendorOrder] = $this->placedVendorOrder(VendorOrderStatus::Cancelled, reservationStatus: InventoryReservationStatus::Released, reserved: 0, reason: 'Private warehouse note');
        $customer = $vendorOrder->order->user;
        $admin = User::factory()->admin()->create();

        $this->actingAs($customer)
            ->get(route('account.orders.show', $vendorOrder->order))
            ->assertOk()
            ->assertSeeText('This seller was unable to fulfill this part of your order.')
            ->assertDontSeeText('Private warehouse note');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $vendorOrder->order))
            ->assertOk()
            ->assertSeeText('Private warehouse note')
            ->assertSeeText('Reservation:')
            ->assertSeeText('Released');
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

    private function placedVendorOrder(
        VendorOrderStatus $status,
        int $quantity = 1,
        int $onHand = 10,
        ?InventoryReservationStatus $reservationStatus = null,
        ?int $reserved = null,
        ?string $reason = null,
    ): array {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->variantFor($vendor, $onHand, $reserved ?? ($reservationStatus === InventoryReservationStatus::Committed || $reservationStatus === InventoryReservationStatus::Released ? 0 : $quantity));
        $method = ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'flat_rate' => 500]);

        $order = $customer->orders()->create([
            'order_number' => 'ORD-'.fake()->unique()->bothify('??????'),
            'customer_address_id' => $address->id,
            'idempotency_key' => fake()->uuid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'currency' => 'USD',
            'items_subtotal' => $variant->base_price * $quantity,
            'shipping_total' => 500,
            'grand_total' => ($variant->base_price * $quantity) + 500,
            'shipping_first_name' => $address->first_name,
            'shipping_last_name' => $address->last_name,
            'shipping_phone' => $address->phone,
            'shipping_address_line_1' => $address->address_line_1,
            'shipping_address_line_2' => $address->address_line_2,
            'shipping_city' => $address->city,
            'shipping_state_region' => $address->state_region,
            'shipping_postal_code' => $address->postal_code,
            'shipping_country_code' => $address->country_code,
            'placed_at' => now(),
        ]);

        $vendorOrder = $this->vendorOrder($order, $vendor, $method, $status, $reason);
        $inventory = $this->addItem($vendorOrder, $quantity, $variant->inventory->on_hand, $variant->inventory->reserved, $variant, $reservationStatus);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::CashOnDelivery,
            'provider' => PaymentMethod::CashOnDelivery->value,
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);

        return [$vendorOrder->refresh()->load(['vendor.user', 'order', 'items.reservation']), $inventory, $payment];
    }

    private function addVendorOrderTo(Order $order, VendorOrderStatus $status): VendorOrder
    {
        $vendor = Vendor::factory()->approved()->create();
        $method = ShippingMethod::factory()->create(['vendor_id' => $vendor->id]);

        $vendorOrder = $this->vendorOrder($order, $vendor, $method, $status);
        $this->addItem($vendorOrder);

        return $vendorOrder;
    }

    private function vendorOrder(Order $order, Vendor $vendor, ShippingMethod $method, VendorOrderStatus $status, ?string $reason = null): VendorOrder
    {
        return $order->vendorOrders()->create([
            'vendor_id' => $vendor->id,
            'shipping_method_id' => $method->id,
            'vendor_order_number' => $order->order_number.'-'.fake()->unique()->numberBetween(10, 99),
            'status' => $status,
            'items_subtotal' => 1000,
            'shipping_total' => 500,
            'grand_total' => 1500,
            'shipping_method_name' => $method->name,
            'shipping_method_code' => $method->code,
            'shipping_method_description' => $method->description,
            'shipping_min_delivery_days' => $method->min_delivery_days,
            'shipping_max_delivery_days' => $method->max_delivery_days,
            'cancelled_at' => $status === VendorOrderStatus::Cancelled ? now() : null,
            'cancelled_by' => $status === VendorOrderStatus::Cancelled ? $vendor->user_id : null,
            'cancellation_reason' => $status === VendorOrderStatus::Cancelled ? $reason : null,
        ]);
    }

    private function addItem(VendorOrder $vendorOrder, int $quantity = 1, int $onHand = 10, int $reserved = 1, ?ProductVariant $variant = null, ?InventoryReservationStatus $reservationStatus = null): Inventory
    {
        $variant ??= $this->variantFor($vendorOrder->vendor, $onHand, $reserved);

        $item = $vendorOrder->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_description' => null,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price' => $variant->base_price,
            'line_total' => $variant->base_price * $quantity,
            'attributes_snapshot' => null,
        ]);

        $item->reservation()->create([
            'inventory_id' => $variant->inventory->id,
            'quantity' => $quantity,
            'status' => $reservationStatus ?? InventoryReservationStatus::Active,
        ]);

        return $variant->inventory->refresh();
    }

    private function variantFor(Vendor $vendor, int $onHand, int $reserved): ProductVariant
    {
        $product = Product::factory()
            ->approved()
            ->create([
                'vendor_id' => $vendor->id,
                'category_id' => Category::factory()->create()->id,
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Approved,
            ]);
        $variant = ProductVariant::factory()->default()->create([
            'product_id' => $product->id,
            'base_price' => 1000,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['on_hand' => $onHand, 'reserved' => $reserved, 'low_stock_threshold' => 2],
        );

        return $variant->load(['product', 'inventory']);
    }
}
