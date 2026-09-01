<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function manage(User $user, Product $product): bool
    {
        return $user->role === UserRole::Vendor
            && $user->vendor?->id === $product->vendor_id;
    }

    public function moderate(User $user, Product $product): bool
    {
        return $user->role === UserRole::Admin;
    }
}
