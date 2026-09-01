<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\MutatesInventory;
use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdjustInventory
{
    use MutatesInventory;

    public function handle(ProductVariant $variant, int $onHand, User $actor, string $reason): Inventory
    {
        $this->ensureNonNegativeOnHand($onHand);
        $this->ensureVariantCanBeMutated($variant);

        return DB::transaction(function () use ($variant, $onHand, $actor, $reason): Inventory {
            app(EnsureInventoryExists::class)->handle($variant);

            $inventory = $this->lockedInventory($variant);
            $before = $inventory->on_hand;
            $this->ensureOnHandCanCoverReserved($onHand, $inventory->reserved, 'on_hand');

            $inventory->forceFill(['on_hand' => $onHand])->save();
            $inventory->movements()->create([
                'product_variant_id' => $variant->id,
                'type' => InventoryMovementType::Adjustment,
                'quantity' => $onHand - $before,
                'before_quantity' => $before,
                'after_quantity' => $onHand,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            return $inventory->refresh();
        });
    }
}
