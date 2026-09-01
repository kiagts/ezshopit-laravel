<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RejectProduct
{
    public function handle(Product $product, User $admin, string $reason): Product
    {
        if ($product->approval_status !== ProductApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval_status' => 'Only pending products can be rejected.']);
        }

        $product->forceFill([
            'status' => ProductStatus::Draft,
            'approval_status' => ProductApprovalStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'rejected_by' => $admin->id,
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        return $product;
    }
}
