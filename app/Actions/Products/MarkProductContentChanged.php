<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;

class MarkProductContentChanged
{
    public function handle(Product $product): void
    {
        if ($product->approval_status !== ProductApprovalStatus::Approved) {
            return;
        }

        $product->forceFill([
            'approval_status' => ProductApprovalStatus::Draft,
            'status' => ProductStatus::Draft,
            'approved_at' => null,
            'approved_by' => null,
        ])->save();
    }
}
