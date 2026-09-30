<?php
namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class VendorLedgerEntryBuilder extends Builder
{
    public function update(array $values)
    {
        throw new \LogicException('Vendor ledger entries are immutable.');
    }

    public function delete()
    {
        throw new \LogicException('Vendor ledger entries are immutable.');
    }

    public function forceDelete()
    {
        throw new \LogicException('Vendor ledger entries are immutable.');
    }
}

