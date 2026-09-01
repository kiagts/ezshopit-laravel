<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddItemToCart
{
    public function __construct(
        private readonly CartResolver $cartResolver,
    ) {}

    public function handle(Request $request, int $variantId, int $quantity): CartItem
    {
        $variant = ProductVariant::query()
            ->with('inventory')
            ->whereKey($variantId)
            ->firstOrFail();

        $this->assertPurchasable($variant, $quantity);

        $cart = $this->cartResolver->current($request, create: true);
        $max = $this->maxQuantity();

        return DB::transaction(function () use ($cart, $variant, $quantity, $max): CartItem {
            $item = $cart->items()
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $newQuantity = min($max, $item->quantity + $quantity);
                $this->assertPurchasable($variant, $newQuantity);
                $item->quantity = $newQuantity;
                $item->save();

                return $item;
            }

            return $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
            ]);
        });
    }

    private function assertPurchasable(ProductVariant $variant, int $quantity): void
    {
        if ($quantity < 1 || $quantity > $this->maxQuantity()) {
            throw ValidationException::withMessages(['quantity' => 'Choose a valid quantity.']);
        }

        if (! $variant->is_active || $variant->trashed()) {
            throw ValidationException::withMessages(['product_variant_id' => 'This option is no longer available.']);
        }

        if (! Product::query()->publiclyVisible()->whereKey($variant->product_id)->exists()) {
            throw ValidationException::withMessages(['product_variant_id' => 'This product is no longer available.']);
        }

        if (! $variant->inventory || $variant->availableQuantity() <= 0) {
            throw ValidationException::withMessages(['quantity' => 'This option is out of stock.']);
        }

        if ($quantity > $variant->availableQuantity()) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity is not currently available.']);
        }
    }

    private function maxQuantity(): int
    {
        return max(1, (int) config('marketplace.cart.max_quantity_per_item', 20));
    }
}
