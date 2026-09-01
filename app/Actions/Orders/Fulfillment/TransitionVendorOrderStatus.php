<?php

namespace App\Actions\Orders\Fulfillment;

use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Enums\VendorOrderStatus;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\Payments\CanFulfillVendorOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransitionVendorOrderStatus
{
    public function __construct(
        private readonly CommitInventoryReservationsForVendorOrder $commitReservations,
        private readonly ReleaseInventoryReservationsForVendorOrder $releaseReservations,
        private readonly RecalculateOrderStatus $recalculateOrderStatus,
        private readonly CanFulfillVendorOrder $canFulfillVendorOrder,
        private readonly RecognizeVendorOrderCommission $recognizeVendorOrderCommission,
    ) {
    }

    public function handle(VendorOrder $vendorOrder, VendorOrderStatus $target, ?User $actor = null, ?string $reason = null): VendorOrder
    {
        $transitioned = DB::transaction(function () use ($vendorOrder, $target, $actor, $reason): VendorOrder {
            $locked = VendorOrder::query()
                ->with('order')
                ->whereKey($vendorOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $target) {
                return $locked->load(['order', 'items.reservation']);
            }

            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Vendor order cannot move from {$locked->status->label()} to {$target->label()}.",
                ]);
            }

            if ($target !== VendorOrderStatus::Cancelled) {
                $eligibility = $this->canFulfillVendorOrder->check($locked);

                if (! $eligibility->allowed) {
                    throw ValidationException::withMessages([
                        'payment' => $eligibility->message ?? 'This order cannot be fulfilled until payment is ready.',
                    ]);
                }
            }

            match ($target) {
                VendorOrderStatus::Confirmed => $this->markConfirmed($locked),
                VendorOrderStatus::Processing => $this->markProcessing($locked),
                VendorOrderStatus::ReadyToShip => $this->markReadyToShip($locked),
                VendorOrderStatus::Shipped => $this->markShipped($locked, $actor),
                VendorOrderStatus::Delivered => $this->markDelivered($locked),
                VendorOrderStatus::Cancelled => $this->markCancelled($locked, $actor, $reason),
                VendorOrderStatus::Pending => throw ValidationException::withMessages(['status' => 'Vendor order cannot be moved back to pending.']),
            };

            $this->recalculateOrderStatus->handle($locked->order);

            return $locked->refresh()->load(['order', 'items.reservation']);
        }, 3);

        if ($target === VendorOrderStatus::Delivered) {
            try {
                $this->recognizeVendorOrderCommission->handle($transitioned);
            } catch (\Throwable $exception) {
                Log::error('Vendor order commission recognition failed after delivery.', [
                    'vendor_order_id' => $transitioned->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $transitioned;
    }

    private function markConfirmed(VendorOrder $vendorOrder): void
    {
        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::Confirmed,
            'confirmed_at' => $vendorOrder->confirmed_at ?? now(),
        ])->save();
    }

    private function markProcessing(VendorOrder $vendorOrder): void
    {
        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::Processing,
            'processing_at' => $vendorOrder->processing_at ?? now(),
        ])->save();
    }

    private function markReadyToShip(VendorOrder $vendorOrder): void
    {
        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::ReadyToShip,
            'ready_to_ship_at' => $vendorOrder->ready_to_ship_at ?? now(),
        ])->save();
    }

    private function markShipped(VendorOrder $vendorOrder, ?User $actor): void
    {
        $this->commitReservations->handle($vendorOrder, $actor);

        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::Shipped,
            'shipped_at' => $vendorOrder->shipped_at ?? now(),
        ])->save();
    }

    private function markDelivered(VendorOrder $vendorOrder): void
    {
        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::Delivered,
            'delivered_at' => $vendorOrder->delivered_at ?? now(),
        ])->save();
    }

    private function markCancelled(VendorOrder $vendorOrder, ?User $actor, ?string $reason): void
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A cancellation reason is required.']);
        }

        $this->releaseReservations->handle($vendorOrder);

        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::Cancelled,
            'cancelled_at' => $vendorOrder->cancelled_at ?? now(),
            'cancelled_by' => $vendorOrder->cancelled_by ?? $actor?->id,
            'cancellation_reason' => $vendorOrder->cancellation_reason ?? $reason,
        ])->save();
    }
}
