<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\MutatesInventory;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class UpdateInventoryThreshold
{
    use MutatesInventory;

    public function handle(ProductVariant $variant, int $threshold): Inventory
    {
        $this->ensureNonNegativeThreshold($threshold);
        $this->ensureVariantCanBeMutated($variant);

        return DB::transaction(function () use ($variant, $threshold): Inventory {
            app(EnsureInventoryExists::class)->handle($variant);

            $inventory = $this->lockedInventory($variant);
            $inventory->forceFill(['low_stock_threshold' => $threshold])->save();

            return $inventory->refresh();
        });
    }
}
