<?php

namespace App\Actions\Products;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\ProductSubmissionValidator;
use Illuminate\Validation\ValidationException;

class SubmitProductForApproval
{
    public function __construct(
        private readonly ProductSubmissionValidator $validator,
        private readonly MarketplaceNotifier $notifier,
    ) {}

    public function handle(Product $product): Product
    {
        if ($product->approval_status === ProductApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval_status' => 'This product is already under review.']);
        }

        if (! in_array($product->approval_status, [ProductApprovalStatus::Draft, ProductApprovalStatus::Rejected], true)) {
            throw ValidationException::withMessages(['approval_status' => 'Only draft or rejected products can be submitted.']);
        }

        $errors = $this->validator->errors($product);

        if ($errors !== []) {
            throw ValidationException::withMessages(['readiness' => $errors]);
        }

        $product->forceFill([
            'status' => ProductStatus::Active,
            'approval_status' => ProductApprovalStatus::Pending,
            'submitted_at' => now(),
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
        ])->save();

        $this->notifier->productSubmitted($product);

        return $product;
    }
}
