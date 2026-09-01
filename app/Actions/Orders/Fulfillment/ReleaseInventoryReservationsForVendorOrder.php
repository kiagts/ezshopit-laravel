<?php

namespace App\Actions\Orders\Fulfillment;

use App\Enums\InventoryReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\VendorOrder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReleaseInventoryReservationsForVendorOrder
{
    public function handle(VendorOrder $vendorOrder): void
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
                || $inventory->reserved < $reservation->quantity) {
                throw ValidationException::withMessages([
                    'inventory' => 'This order cannot be cancelled because its inventory reservation is not active.',
                ]);
            }
        }

        foreach ($reservations as $reservation) {
            /** @var Inventory $inventory */
            $inventory = $inventories[$reservation->inventory_id];

            $inventory->forceFill([
                'reserved' => $inventory->reserved - $reservation->quantity,
            ])->save();

            $reservation->forceFill(['status' => InventoryReservationStatus::Released])->save();
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
                'inventory' => 'Every order item must have exactly one reservation before cancellation.',
            ]);
        }
    }
}
