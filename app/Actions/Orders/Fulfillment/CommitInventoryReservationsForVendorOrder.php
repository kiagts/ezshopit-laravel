<?php

namespace App\Actions\Orders\Fulfillment;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CommitInventoryReservationsForVendorOrder
{
    public function handle(VendorOrder $vendorOrder, ?User $actor = null): void
    {
        $reservations = $this->lockedReservations($vendorOrder);
        $this->ensureAllItemsHaveReservations($vendorOrder, $reservations);

        $inventories = Inventory::query()
            ->whereIn('id', $reservations->pluck('inventory_id')->unique()->sort()->values()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservations as $reservation) {
            $inventory = $inventories[$reservation->inventory_id] ?? null;

            if (! $inventory
                || $reservation->status !== InventoryReservationStatus::Active
                || $inventory->reserved < $reservation->quantity
                || $inventory->on_hand < $reservation->quantity) {
                throw ValidationException::withMessages([
                    'inventory' => 'This order cannot be shipped because its inventory reservation is not valid.',
                ]);
            }
        }

        foreach ($reservations as $reservation) {
            /** @var Inventory $inventory */
            $inventory = $inventories[$reservation->inventory_id];
            $before = $inventory->on_hand;
            $after = $before - $reservation->quantity;
            $reservedAfter = $inventory->reserved - $reservation->quantity;

            if ($after < 0 || $reservedAfter < 0 || $reservedAfter > $after) {
                throw ValidationException::withMessages([
                    'inventory' => 'This order cannot be shipped because inventory invariants would be violated.',
                ]);
            }

            $inventory->forceFill([
                'on_hand' => $after,
                'reserved' => $reservedAfter,
            ])->save();

            $inventory->movements()->create([
                'product_variant_id' => $inventory->product_variant_id,
                'type' => InventoryMovementType::OrderFulfillment,
                'quantity' => -$reservation->quantity,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reason' => "Order {$vendorOrder->order->order_number} / Vendor Order {$vendorOrder->vendor_order_number} shipped",
                'reference_type' => VendorOrder::class,
                'reference_id' => $vendorOrder->id,
                'created_by' => $actor?->id,
            ]);

            $reservation->forceFill(['status' => InventoryReservationStatus::Committed])->save();
        }
    }

    /**
     * @return Collection<int, InventoryReservation>
     */
    private function lockedReservations(VendorOrder $vendorOrder): Collection
    {
        return InventoryReservation::query()
            ->select('inventory_reservations.*')
            ->join('order_items', 'order_items.id', '=', 'inventory_reservations.order_item_id')
            ->where('order_items.vendor_order_id', $vendorOrder->id)
            ->orderBy('inventory_reservations.inventory_id')
            ->orderBy('inventory_reservations.id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, InventoryReservation>  $reservations
     */
    private function ensureAllItemsHaveReservations(VendorOrder $vendorOrder, Collection $reservations): void
    {
        if ($reservations->count() !== $vendorOrder->items()->count()) {
            throw ValidationException::withMessages([
                'inventory' => 'Every order item must have exactly one reservation before shipment.',
            ]);
        }
    }
}
