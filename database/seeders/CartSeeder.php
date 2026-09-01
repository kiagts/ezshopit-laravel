<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\CartItem;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@example.com')->first();

        if (! $customer) {
            return;
        }

        $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id], ['token' => null]);

        $items = [
            'IPH17-BLK-8-256' => 1,
            'SGS26-BLK-12-256' => 2,
            'DIN14-SLV-16-512' => 1,
            'BOSCH-MIXER-COMPACT' => 1,
        ];

        ProductVariant::query()
            ->whereIn('sku', array_keys($items))
            ->get(['id', 'sku'])
            ->each(fn (ProductVariant $variant): CartItem => CartItem::query()->updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'product_variant_id' => $variant->id,
                ],
                ['quantity' => $items[$variant->sku]],
            ));
    }
}
