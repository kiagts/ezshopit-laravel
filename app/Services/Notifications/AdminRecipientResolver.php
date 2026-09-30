<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AdminRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('id')
            ->get();
    }
}
