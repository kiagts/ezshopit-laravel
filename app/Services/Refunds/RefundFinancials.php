<?php

namespace App\Services\Refunds;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\VendorOrder;

class RefundFinancials
{
    public function summaryForPayment(Payment $payment): PaymentFinancialSummary
    {
        $refunds = $payment->relationLoaded('refunds')
            ? $payment->refunds
            : $payment->refunds()->get();

        return new PaymentFinancialSummary(
            originalAmount: (int) $payment->amount,
            refundedAmount: (int) $refunds->where('status', RefundStatus::Succeeded)->sum('amount'),
            pendingRefundAmount: (int) $refunds
                ->filter(fn ($refund): bool => $refund->status->protectsCapacity())
                ->sum('amount'),
        );
    }

    public function protectedItemAmount(int $orderItemId): int
    {
        return (int) \App\Models\RefundAllocation::query()
            ->where('order_item_id', $orderItemId)
            ->whereHas('refund', fn ($query) => $query->whereIn('status', [
                RefundStatus::Pending->value,
                RefundStatus::Processing->value,
                RefundStatus::Succeeded->value,
            ]))
            ->sum('amount');
    }

    public function protectedItemQuantity(int $orderItemId): int
    {
        return (int) \App\Models\RefundAllocation::query()
            ->where('order_item_id', $orderItemId)
            ->whereNotNull('quantity')
            ->whereHas('refund', fn ($query) => $query->whereIn('status', [
                RefundStatus::Pending->value,
                RefundStatus::Processing->value,
                RefundStatus::Succeeded->value,
            ]))
            ->sum('quantity');
    }

    public function protectedShippingAmount(int $vendorOrderId): int
    {
        return (int) \App\Models\RefundAllocation::query()
            ->where('vendor_order_id', $vendorOrderId)
            ->where('type', \App\Enums\RefundAllocationType::Shipping->value)
            ->whereHas('refund', fn ($query) => $query->whereIn('status', [
                RefundStatus::Pending->value,
                RefundStatus::Processing->value,
                RefundStatus::Succeeded->value,
            ]))
            ->sum('amount');
    }

    public function successfulVendorRefundAmount(VendorOrder $vendorOrder): int
    {
        $allocations = $vendorOrder->relationLoaded('refundAllocations')
            ? $vendorOrder->refundAllocations
            : $vendorOrder->refundAllocations()->with('refund')->get();

        return (int) $allocations
            ->filter(fn ($allocation): bool => $allocation->refund?->status === RefundStatus::Succeeded)
            ->sum('amount');
    }
}
