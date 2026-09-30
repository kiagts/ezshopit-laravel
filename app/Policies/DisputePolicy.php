<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Dispute;
use App\Models\User;

class DisputePolicy
{
    public function view(User $user, Dispute $dispute): bool
    {
        return $user->role === UserRole::Admin || ($user->role === UserRole::Customer && $dispute->opened_by === $user->id)
            || ($user->role === UserRole::Vendor && $dispute->vendorOrder?->vendor?->user_id === $user->id && $dispute->vendorOrder->vendor->isApproved());
    }

    public function message(User $user, Dispute $dispute): bool
    {
        return $this->view($user, $dispute) && $dispute->status->active();
    }

    public function manage(User $user, Dispute $dispute): bool
    {
        return $user->role === UserRole::Admin;
    }
}
