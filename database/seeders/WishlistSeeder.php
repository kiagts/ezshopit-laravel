<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;

class WishlistSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@example.com')->first();

        if (! $customer) {
            return;
        }

        $slugs = [
            'apple-iphone-17-pro',
            'samsung-galaxy-s26-ultra',
            'xiaomi-redmi-note-16',
            'skechers-go-walk-sneaker',
            'logitech-mx-master-4',
        ];

        Product::query()
            ->whereIn('slug', $slugs)
            ->get(['id', 'slug'])
            ->each(fn (Product $product): WishlistItem => WishlistItem::query()->firstOrCreate([
                'user_id' => $customer->id,
                'product_id' => $product->id,
            ]));
    }
}
