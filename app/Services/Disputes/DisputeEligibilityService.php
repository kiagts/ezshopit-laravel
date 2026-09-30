<?php

namespace App\Services\Disputes;

use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DisputeEligibilityService
{
    public function context(Order $order, User $actor, array $data): array
    {
        abort_unless($actor->isCustomer() && $order->user_id === $actor->id, 403);
        $id = $data['vendor_order_id'] ?? null;
        if (! $id && $order->vendorOrders()->count() === 1) {
            $id = $order->vendorOrders()->value('id');
        }
        $vendorOrder = $order->vendorOrders()->whereKey($id)->first();
        if (! $vendorOrder) {
            $this->invalid('Choose a seller order belonging to this order.');
        }
        $return = empty($data['return_request_id']) ? null : ReturnRequest::find($data['return_request_id']);
        if (! empty($data['return_request_id']) && (! $return || $return->order_id !== $order->id || $return->vendor_order_id !== $vendorOrder->id || $return->user_id !== $actor->id)) {
            $this->invalid('Return does not belong to this seller and customer.');
        }
        $refund = empty($data['refund_id']) ? null : Refund::find($data['refund_id']);
        if (! empty($data['refund_id']) && (! $refund || $refund->order_id !== $order->id || ! $refund->allocations()->where('vendor_order_id', $vendorOrder->id)->exists() || $refund->allocations()->whereNotNull('vendor_order_id')->where('vendor_order_id', '!=', $vendorOrder->id)->exists())) {
            $this->invalid('Choose a refund scoped to this seller order.');
        }
        if ($return && $refund && $refund->return_request_id && $refund->return_request_id !== $return->id) {
            $this->invalid('Return and refund references disagree.');
        }

        return [$vendorOrder, $return, $refund];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['dispute' => $message]);
    }
}
