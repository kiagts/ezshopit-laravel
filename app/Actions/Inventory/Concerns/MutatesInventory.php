<?php

namespace App\Actions\Inventory\Concerns;

use App\Enums\ProductStatus;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

trait MutatesInventory
{
    private function ensureVariantCanBeMutated(ProductVariant $variant): void
    {
        $variant->loadMissing('product');

        if ($variant->product->status === ProductStatus::Archived) {
            throw ValidationException::withMessages(['inventory' => 'Archived products cannot have inventory changes.']);
        }
    }

    private function lockedInventory(ProductVariant $variant): Inventory
    {
        return Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureOnHandCanCoverReserved(int $onHand, int $reserved, string $field = 'quantity'): void
    {
        if ($onHand < $reserved) {
            throw ValidationException::withMessages([$field => 'On-hand stock cannot be reduced below reserved stock.']);
        }
    }

    private function ensurePositiveQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }
    }

    private function ensureNonNegativeOnHand(int $onHand): void
    {
        if ($onHand < 0) {
            throw ValidationException::withMessages(['on_hand' => 'On-hand stock cannot be negative.']);
        }
    }

    private function ensureNonNegativeThreshold(int $threshold): void
    {
        if ($threshold < 0) {
            throw ValidationException::withMessages(['low_stock_threshold' => 'Low-stock threshold cannot be negative.']);
        }
    }
}
