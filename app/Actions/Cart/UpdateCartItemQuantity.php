<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class UpdateCartItemQuantity
{
    public function handle(CartItem $item, int $quantity): CartItem
    {
        if ($quantity < 1 || $quantity > $this->maxQuantity()) {
            throw ValidationException::withMessages(['quantity' => 'Choose a valid quantity.']);
        }

        $item->loadMissing('variant.inventory');
        $variant = $item->variant;

        if (! $variant || ! $variant->is_active || $variant->trashed()) {
            throw ValidationException::withMessages(['quantity' => 'This option is no longer available.']);
        }

        if (! Product::query()->publiclyVisible()->whereKey($variant->product_id)->exists()) {
            throw ValidationException::withMessages(['quantity' => 'This product is no longer available.']);
        }

        if (! $variant->inventory || $variant->availableQuantity() <= 0) {
            throw ValidationException::withMessages(['quantity' => 'This option is out of stock.']);
        }

        if ($quantity > $variant->availableQuantity()) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity is not currently available.']);
        }

        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    private function maxQuantity(): int
    {
        return max(1, (int) config('marketplace.cart.max_quantity_per_item', 20));
    }
}
