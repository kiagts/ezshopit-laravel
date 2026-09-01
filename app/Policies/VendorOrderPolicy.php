<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;

class VendorOrderPolicy
{
    public function view(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    public function confirm(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    public function process(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    public function readyToShip(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    public function ship(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    public function deliver(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    public function cancel(User $user, VendorOrder $vendorOrder): bool
    {
        return $this->ownsVendorOrder($user, $vendorOrder);
    }

    private function ownsVendorOrder(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->isVendor() && $user->vendor?->id === $vendorOrder->vendor_id;
    }
}
