<?php

namespace App\Policies;

use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;

class ReturnRequestPolicy
{
    public function create(User $user, Order $order): bool
    {
        return $user->role === UserRole::Customer && $order->user_id === $user->id;
    }

    public function view(User $user, ReturnRequest $return): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Customer && $return->user_id === $user->id)
            || $this->manage($user, $return);
    }

    public function manage(User $user, ReturnRequest $return): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Vendor
                && $return->vendorOrder?->vendor?->user_id === $user->id
                && $return->vendorOrder->vendor->status === VendorStatus::Approved);
    }

    public function cancel(User $user, ReturnRequest $return): bool
    {
        return $user->role === UserRole::Customer && $return->user_id === $user->id
            && $return->status === ReturnStatus::Requested;
    }

    public function inspect(User $user, ReturnRequest $return): bool
    {
        return $this->manage($user, $return);
    }

    public function upload(User $user, ReturnRequest $return): bool
    {
        return $user->role === UserRole::Customer && $return->user_id === $user->id
            && in_array($return->status, [ReturnStatus::Requested, ReturnStatus::Approved, ReturnStatus::AwaitingReturn], true);
    }
}
