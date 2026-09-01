<?php

namespace App\Actions\Orders\Fulfillment;

use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Order;

class RecalculateOrderStatus
{
    public function handle(Order $order): Order
    {
        $order->unsetRelation('vendorOrders');
        $order->load('vendorOrders:id,order_id,status');

        $statuses = $order->vendorOrders->pluck('status');

        $status = $this->aggregate($statuses->all());
        $changes = [];

        if ($order->status !== $status) {
            $changes['status'] = $status;
        }

        if ($status === OrderStatus::Completed && $order->completed_at === null) {
            $changes['completed_at'] = now();
        }

        if ($status === OrderStatus::Cancelled && $order->cancelled_at === null) {
            $changes['cancelled_at'] = now();
        }

        if ($changes !== []) {
            $order->forceFill($changes)->save();
        }

        return $order->refresh();
    }

    /**
     * @param  array<int, VendorOrderStatus>  $statuses
     */
    private function aggregate(array $statuses): OrderStatus
    {
        if ($statuses === [] || $this->all($statuses, VendorOrderStatus::Pending)) {
            return OrderStatus::Pending;
        }

        if ($this->all($statuses, VendorOrderStatus::Cancelled)) {
            return OrderStatus::Cancelled;
        }

        if ($this->allIn($statuses, [VendorOrderStatus::Delivered, VendorOrderStatus::Cancelled])
            && $this->contains($statuses, VendorOrderStatus::Delivered)) {
            return OrderStatus::Completed;
        }

        if ($this->all($statuses, VendorOrderStatus::Confirmed)) {
            return OrderStatus::Confirmed;
        }

        return OrderStatus::Processing;
    }

    /**
     * @param  array<int, VendorOrderStatus>  $statuses
     */
    private function all(array $statuses, VendorOrderStatus $expected): bool
    {
        foreach ($statuses as $status) {
            if ($status !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, VendorOrderStatus>  $statuses
     * @param  array<int, VendorOrderStatus>  $allowed
     */
    private function allIn(array $statuses, array $allowed): bool
    {
        foreach ($statuses as $status) {
            if (! in_array($status, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, VendorOrderStatus>  $statuses
     */
    private function contains(array $statuses, VendorOrderStatus $expected): bool
    {
        return in_array($expected, $statuses, true);
    }
}
