<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ArchiveProduct
{
    public function handle(Product $product): Product
    {
        if ($product->approval_status === ProductApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval_status' => 'Products under review cannot be archived.']);
        }

        $product->forceFill(['status' => ProductStatus::Archived])->save();

        return $product;
    }
}
