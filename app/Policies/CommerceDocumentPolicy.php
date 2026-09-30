<?php

namespace App\Policies;

use App\Models\CommerceDocument;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorOrder;

class CommerceDocumentPolicy
{
    public function order(User $u, Order $o): bool
    {
        return $u->isAdmin() || ($u->isCustomer() && $o->user_id === $u->id);
    }

    public function seller(User $u, VendorOrder $v): bool
    {
        return $u->isAdmin() || ($u->isVendor() && $v->vendor->user_id === $u->id);
    }

    public function refund(User $u, Refund $r): bool
    {
        return $this->order($u, $r->order);
    }

    public function view(User $u, CommerceDocument $d): bool
    {
        return $d->vendor_order_id ? $this->seller($u, $d->vendorOrder) : $this->order($u, $d->order);
    }
}
