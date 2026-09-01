<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;
use App\Models\ProductVariant;

class EnsureInventoryExists
{
    public function handle(ProductVariant $variant): Inventory
    {
        return $variant->inventory()->firstOrCreate([], [
            'on_hand' => 0,
            'reserved' => 0,
            'low_stock_threshold' => (int) config('marketplace.inventory.default_low_stock_threshold', 5),
        ]);
    }
}
