<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductSubmissionValidator;
use Illuminate\Validation\ValidationException;

class ApproveProduct
{
    public function __construct(private readonly ProductSubmissionValidator $validator) {}

    public function handle(Product $product, User $admin): Product
    {
        if ($product->approval_status !== ProductApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval_status' => 'Only pending products can be approved.']);
        }

        $errors = $this->validator->errors($product);

        if ($errors !== []) {
            throw ValidationException::withMessages(['readiness' => $errors]);
        }

        $product->forceFill([
            'approval_status' => ProductApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'published_at' => $product->published_at ?? now(),
        ])->save();

        return $product;
    }
}
