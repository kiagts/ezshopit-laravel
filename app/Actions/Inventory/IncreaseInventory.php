<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\MutatesInventory;
use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncreaseInventory
{
    use MutatesInventory;

    public function handle(ProductVariant $variant, int $quantity, ?User $actor, ?string $reason = null, ?string $referenceType = null, ?int $referenceId = null): Inventory
    {
        $this->ensurePositiveQuantity($quantity);
        $this->ensureVariantCanBeMutated($variant);

        return DB::transaction(function () use ($variant, $quantity, $actor, $reason, $referenceType, $referenceId): Inventory {
            app(EnsureInventoryExists::class)->handle($variant);

            $inventory = $this->lockedInventory($variant);
            $before = $inventory->on_hand;
            $after = $before + $quantity;
            $type = $inventory->movements()->exists() ? InventoryMovementType::Increase : InventoryMovementType::Initial;

            $inventory->forceFill(['on_hand' => $after])->save();
            $inventory->movements()->create([
                'product_variant_id' => $variant->id,
                'type' => $type,
                'quantity' => $quantity,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reason' => $reason,
                'created_by' => $actor?->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return $inventory->refresh();
        });
    }
}
