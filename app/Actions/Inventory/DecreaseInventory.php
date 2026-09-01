<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\MutatesInventory;
use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DecreaseInventory
{
    use MutatesInventory;

    public function handle(ProductVariant $variant, int $quantity, ?User $actor, ?string $reason = null): Inventory
    {
        $this->ensurePositiveQuantity($quantity);
        $this->ensureVariantCanBeMutated($variant);

        return DB::transaction(function () use ($variant, $quantity, $actor, $reason): Inventory {
            app(EnsureInventoryExists::class)->handle($variant);

            $inventory = $this->lockedInventory($variant);
            $before = $inventory->on_hand;
            $after = $before - $quantity;
            $this->ensureOnHandCanCoverReserved($after, $inventory->reserved);

            $inventory->forceFill(['on_hand' => $after])->save();
            $inventory->movements()->create([
                'product_variant_id' => $variant->id,
                'type' => InventoryMovementType::Decrease,
                'quantity' => -$quantity,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reason' => $reason,
                'created_by' => $actor?->id,
            ]);

            return $inventory->refresh();
        });
    }
}
