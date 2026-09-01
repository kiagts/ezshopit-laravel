<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class DeactivateProduct
{
    public function handle(Product $product): Product
    {
        if ($product->approval_status !== ProductApprovalStatus::Approved) {
            throw ValidationException::withMessages(['approval_status' => 'Only approved products can be deactivated.']);
        }

        $product->forceFill(['status' => ProductStatus::Inactive])->save();

        return $product;
    }
}
