<?php

namespace App\Actions\Orders;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PromotionFundingSource;
use App\Enums\VendorOrderStatus;
use App\Actions\Payments\CreatePaymentForOrder;
use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\OrderDiscountAllocation;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\Cart\CartResolver;
use App\Services\Cart\EvaluateCart;
use App\Services\Checkout\CheckoutSession;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\Promotions\PromotionEvaluation;
use App\Services\Promotions\PromotionEvaluator;
use App\Services\Shipping\CheckoutShipping;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly EvaluateCart $evaluateCart,
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutShipping $checkoutShipping,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly CreatePaymentForOrder $createPaymentForOrder,
        private readonly PromotionEvaluator $promotionEvaluator,
        private readonly MarketplaceNotifier $notifier,
    ) {
    }

    public function handle(Request $request, string $idempotencyKey): Order
    {
        $existing = $this->existingOrder($request, $idempotencyKey);

        if ($existing) {
            return $existing;
        }

        try {
            $order = DB::transaction(function () use ($request, $idempotencyKey): Order {
                $existing = $this->existingOrder($request, $idempotencyKey);

                if ($existing) {
                    return $existing;
                }

                $evaluation = $this->validCartEvaluation($request);
                $evaluation = $this->lockAndRevalidatePromotion($request, $evaluation);
                $address = $this->selectedAddress($request);
                $shippingData = $this->validShippingData($request, $evaluation, $address);
                $paymentMethod = $this->validPaymentMethod($request);
                $expectedKey = $this->checkoutSession->idempotencyKey($request, $this->checkoutSession->signature($evaluation, $address, $shippingData, $paymentMethod));

                if (! hash_equals($expectedKey, $idempotencyKey)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Refresh checkout and try placing the order again.',
                    ]);
                }

                $items = $evaluation['items'];
                $inventories = $this->lockInventories($items);
                $this->assertInventoryAvailable($items, $inventories);

                $order = $this->createOrder($request, $idempotencyKey, $address, $evaluation, $shippingData);
                $shippingGroups = collect($shippingData['shipping_groups'])->keyBy(fn (array $group): int => $group['vendor']->id);
                $orderItemsByCartItem = collect();
                $vendorOrdersByVendor = collect();

                foreach ($evaluation['groups']->values() as $index => $group) {
                    $shippingGroup = $shippingGroups[$group['vendor']->id];
                    $vendorOrder = $this->createVendorOrder($order, $group, $shippingGroup, $index + 1);
                    $vendorOrdersByVendor->put((int) $group['vendor']->id, $vendorOrder);

                    foreach ($group['items'] as $row) {
                        $inventory = $inventories[(int) $row['variant']->id];
                        $orderItem = $this->createOrderItem($vendorOrder, $row);
                        $orderItemsByCartItem->put((int) $row['item']->id, $orderItem);

                        $inventory->increment('reserved', (int) $row['quantity']);
                        $orderItem->reservation()->create([
                            'inventory_id' => $inventory->id,
                            'quantity' => (int) $row['quantity'],
                            'status' => InventoryReservationStatus::Active,
                        ]);
                    }
                }

                $this->createDiscountSnapshots($order, $evaluation, $orderItemsByCartItem, $vendorOrdersByVendor);
                foreach ($evaluation['items'] as $row) {
                    $orderItemsByCartItem[(int) $row['item']->id]->update(['tax_total' => (int) ($row['tax_total'] ?? 0)]);
                }
                app(\App\Services\WalletService::class)->applyToOrder($order, (bool) $request->session()->get('checkout.use_wallet', false));
                $giftCode = $request->session()->get(\App\Services\Checkout\CheckoutSession::GIFT_CARD_KEY);
                if (filled($giftCode)) {
                    $remaining = max(0, $order->grand_total - $order->wallet_applied_total);
                    $applied = app(\App\Services\GiftCardService::class)->redeem($order, (string) $giftCode, $remaining);
                    $order->forceFill(['gift_card_applied_total' => $applied, 'gift_card_last_four' => substr(app(\App\Services\GiftCardService::class)->normalize((string) $giftCode), -4)])->save();
                }
                $this->createPaymentForOrder->handle($order, $paymentMethod);

                CartItem::query()
                    ->whereIn('id', $items->pluck('item.id')->all())
                    ->delete();
                $evaluation['cart']?->forceFill(['promotion_id' => null])->save();

                return $order;
            }, 3);
        } catch (QueryException $exception) {
            $order = $this->existingOrder($request, $idempotencyKey);

            if (! $order) {
                throw $exception;
            }
        }

        $this->checkoutSession->clear($request);
        $this->notifier->orderPlaced($order);

        return $order->load(['payment.attempts', 'vendorOrders.items.reservation']);
    }

    private function existingOrder(Request $request, string $idempotencyKey): ?Order
    {
        return Order::query()
            ->where('user_id', $request->user()->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validCartEvaluation(Request $request): array
    {
        $evaluation = $this->evaluateCart->evaluate($this->cartResolver->current($request));

        if ($evaluation['line_count'] === 0) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        if ($evaluation['has_blocking_issues']) {
            throw ValidationException::withMessages(['cart' => 'Some items in your cart need attention before checkout.']);
        }

        return $evaluation;
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return array<string, mixed>
     */
    private function lockAndRevalidatePromotion(Request $request, array $evaluation): array
    {
        $appliedPromotion = $evaluation['promotion_evaluation']['promotion'] ?? null;

        if (! $appliedPromotion) {
            return $evaluation;
        }

        $promotion = Promotion::query()
            ->with(['products:id', 'categories:id'])
            ->whereKey($appliedPromotion->id)
            ->lockForUpdate()
            ->first();

        if (! $promotion) {
            throw ValidationException::withMessages(['promotion' => 'This coupon is no longer available.']);
        }

        $promotionEvaluation = $this->promotionEvaluator->evaluate($promotion, $evaluation['items'], $request->user());

        if (! $promotionEvaluation->valid) {
            throw ValidationException::withMessages(['promotion' => $promotionEvaluation->message ?? 'This coupon can no longer be used.']);
        }

        return $this->withPromotionEvaluation($evaluation, $promotionEvaluation);
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return array<string, mixed>
     */
    private function withPromotionEvaluation(array $evaluation, PromotionEvaluation $promotionEvaluation): array
    {
        $allocationsByItem = $promotionEvaluation->allocations->keyBy('cart_item_id');
        $items = $evaluation['items']
            ->map(function (array $row) use ($allocationsByItem): array {
                $allocation = $allocationsByItem[(int) $row['item']->id] ?? null;
                $discount = (int) ($allocation['amount'] ?? 0);
                $isMarketplace = $allocation && $allocation['funding_source'] === PromotionFundingSource::Marketplace;
                $isVendor = $allocation && $allocation['funding_source'] === PromotionFundingSource::Vendor;

                $row['discount_total'] = $discount;
                $row['marketplace_discount_total'] = $isMarketplace ? $discount : 0;
                $row['vendor_discount_total'] = $isVendor ? $discount : 0;
                $row['net_line_total'] = max(0, (int) $row['line_total'] - $discount);

                return $row;
            })
            ->values();

        $groups = $items
            ->groupBy(fn (array $item): string => (string) ($item['vendor']?->id ?? 'unavailable'))
            ->map(function (Collection $items): array {
                $vendor = $items->first()['vendor'];

                return [
                    'vendor' => $vendor,
                    'items' => $items->values(),
                    'subtotal' => $items->sum('line_total'),
                    'discount_total' => $items->sum('discount_total'),
                    'marketplace_discount_total' => $items->sum('marketplace_discount_total'),
                    'vendor_discount_total' => $items->sum('vendor_discount_total'),
                    'net_subtotal' => $items->sum('net_line_total'),
                    'has_blocking_issue' => $items->contains(fn (array $item): bool => $item['status'] !== 'valid'),
                    'first_saved_at' => $items->min('created_at'),
                ];
            })
            ->sortBy('first_saved_at')
            ->values();

        $subtotal = (int) $items->sum('line_total');
        $discount = (int) $items->sum('discount_total');

        return array_merge($evaluation, [
            'items' => $items,
            'groups' => $groups,
            'discount_total' => $discount,
            'total_after_discount' => max(0, $subtotal - $discount),
            'promotion_evaluation' => $promotionEvaluation->toArray(),
        ]);
    }

    private function selectedAddress(Request $request): CustomerAddress
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
        $address = $this->checkoutSession->selectedAddress($request, $addresses);

        if (! $address) {
            throw ValidationException::withMessages(['address' => 'Select a delivery address before placing your order.']);
        }

        $this->checkoutSession->selectAddress($request, $address);

        return $address;
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return array<string, mixed>
     */
    private function validShippingData(Request $request, array $evaluation, CustomerAddress $address): array
    {
        $shippingData = $this->checkoutShipping->build($request, $evaluation, $address);

        if (! $shippingData['shipping_ready']) {
            throw ValidationException::withMessages(['shipping' => 'Choose an available delivery method for every seller.']);
        }

        $selections = $this->checkoutSession->shippingMethods($request);

        foreach ($shippingData['shipping_groups'] as $group) {
            $vendorId = $group['vendor']->id;
            $selected = $group['selected_shipping_method'];

            if (! $selected || ($selections[$vendorId] ?? null) !== $selected->id) {
                throw ValidationException::withMessages(['shipping' => 'Refresh checkout and confirm delivery options before placing your order.']);
            }
        }

        return $shippingData;
    }

    private function validPaymentMethod(Request $request): string
    {
        $method = $this->checkoutSession->paymentMethod($request);

        if (! $method && $request->session()->get('checkout.use_wallet', false)) {
            return 'wallet';
        }
        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => 'Choose a payment method before placing your order.']);
        }

        return $method;
    }

    private function lockInventories(Collection $items): Collection
    {
        return Inventory::query()
            ->whereIn('product_variant_id', $items->pluck('variant.id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all())
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');
    }

    private function assertInventoryAvailable(Collection $items, Collection $inventories): void
    {
        foreach ($items as $row) {
            $inventory = $inventories[(int) $row['variant']->id] ?? null;

            if (! $inventory || $inventory->availableQuantity() < (int) $row['quantity']) {
                throw ValidationException::withMessages([
                    'cart' => "{$row['product']->name} no longer has enough stock available.",
                ]);
            }
        }
    }

    private function createOrder(Request $request, string $idempotencyKey, CustomerAddress $address, array $evaluation, array $shippingData): Order
    {
        return $request->user()->orders()->create([
            'order_number' => $this->orderNumberGenerator->generate(),
            'customer_address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'currency' => app(\App\Services\CurrencyContext::class)->current($request)->code,
            'items_subtotal' => (int) $evaluation['subtotal'],
            'discount_total' => (int) $evaluation['discount_total'],
            'shipping_total' => (int) $shippingData['shipping_total'],
            'tax_total' => (int) ($evaluation['tax_total'] ?? 0),
            'grand_total' => (int) $evaluation['total_after_discount'] + (int) ($evaluation['tax_total'] ?? 0) + (int) $shippingData['shipping_total'],
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
    }

    private function createVendorOrder(Order $order, array $group, array $shippingGroup, int $sequence): \App\Models\VendorOrder
    {
        /** @var ShippingMethod $method */
        $method = $shippingGroup['selected_shipping_method'];

        return $order->vendorOrders()->create([
            'vendor_id' => $group['vendor']->id,
            'shipping_method_id' => $method->id,
            'vendor_order_number' => $order->order_number.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'status' => VendorOrderStatus::Pending,
            'items_subtotal' => (int) $group['subtotal'],
            'discount_total' => (int) $group['discount_total'],
            'marketplace_discount_total' => (int) $group['marketplace_discount_total'],
            'vendor_discount_total' => (int) $group['vendor_discount_total'],
            'shipping_total' => (int) $shippingGroup['shipping_charge'],
            'grand_total' => (int) $group['net_subtotal'] + (int) $shippingGroup['shipping_charge'],
            'shipping_method_name' => $method->name,
            'shipping_method_code' => $method->code,
            'shipping_method_description' => $method->description,
            'shipping_min_delivery_days' => $method->min_delivery_days,
            'shipping_max_delivery_days' => $method->max_delivery_days,
        ]);
    }

    private function createOrderItem(\App\Models\VendorOrder $vendorOrder, array $row): \App\Models\OrderItem
    {
        /** @var ProductVariant $variant */
        $variant = $row['variant'];

        return $vendorOrder->items()->create([
            'product_id' => $row['product']->id,
            'product_variant_id' => $variant->id,
            'product_name' => $row['product']->name,
            'variant_description' => $row['variant_label'],
            'sku' => $variant->sku,
            'quantity' => (int) $row['quantity'],
            'unit_price' => (int) $row['unit_price'],
            'line_total' => (int) $row['line_total'],
            'discount_total' => (int) $row['discount_total'],
            'marketplace_discount_total' => (int) $row['marketplace_discount_total'],
            'vendor_discount_total' => (int) $row['vendor_discount_total'],
            'net_line_total' => (int) $row['net_line_total'],
            'attributes_snapshot' => $this->attributeSnapshot($variant),
        ]);
    }

    private function createDiscountSnapshots(Order $order, array $evaluation, Collection $orderItemsByCartItem, Collection $vendorOrdersByVendor): void
    {
        $promotion = $evaluation['promotion_evaluation']['promotion'] ?? null;
        $discount = (int) ($evaluation['discount_total'] ?? 0);

        if (! $promotion || $discount <= 0) {
            return;
        }

        foreach ($evaluation['promotion_evaluation']['allocations'] as $allocation) {
            $orderItem = $orderItemsByCartItem[(int) $allocation['cart_item_id']] ?? null;
            $vendorOrder = $vendorOrdersByVendor[(int) $allocation['vendor_id']] ?? null;

            if (! $orderItem || ! $vendorOrder) {
                continue;
            }

            OrderDiscountAllocation::query()->create([
                'order_id' => $order->id,
                'vendor_order_id' => $vendorOrder->id,
                'order_item_id' => $orderItem->id,
                'promotion_id' => $promotion->id,
                'promotion_code' => $promotion->code,
                'promotion_name' => $promotion->name,
                'funding_source' => $promotion->funding_source,
                'amount' => (int) $allocation['amount'],
            ]);
        }

        $order->promotionRedemption()->create([
            'promotion_id' => $promotion->id,
            'user_id' => $order->user_id,
            'code_snapshot' => $promotion->code,
            'funding_source' => $promotion->funding_source,
            'discount_amount' => $discount,
            'currency' => $order->currency,
            'redeemed_at' => now(),
        ]);
    }

    private function attributeSnapshot(ProductVariant $variant): ?array
    {
        if ($variant->is_default || $variant->combination_key === 'default') {
            return null;
        }

        $values = $variant->relationLoaded('attributeValues')
            ? $variant->attributeValues
            : $variant->attributeValues()->with('productAttribute')->get();

        $snapshot = $values
            ->sortBy(fn (ProductAttributeValue $value): array => [
                $value->productAttribute?->sort_order ?? 0,
                $value->productAttribute?->name ?? '',
            ])
            ->map(fn (ProductAttributeValue $value): array => [
                'attribute_code' => $value->productAttribute?->code,
                'attribute_name' => $value->productAttribute?->name,
                'value_code' => $value->code,
                'value_label' => $value->value,
            ])
            ->values()
            ->all();

        return $snapshot === [] ? null : $snapshot;
    }
}
