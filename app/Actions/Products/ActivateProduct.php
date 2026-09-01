<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\ProductSubmissionValidator;
use Illuminate\Validation\ValidationException;

class ActivateProduct
{
    public function __construct(private readonly ProductSubmissionValidator $validator) {}

    public function handle(Product $product): Product
    {
        if ($product->approval_status !== ProductApprovalStatus::Approved) {
            throw ValidationException::withMessages(['approval_status' => 'Only approved products can be activated.']);
        }

        $errors = $this->validator->errors($product);

        if ($errors !== []) {
            throw ValidationException::withMessages(['readiness' => $errors]);
        }

        $product->forceFill([
            'status' => ProductStatus::Active,
            'published_at' => $product->published_at ?? now(),
        ])->save();

        return $product;
    }
}
