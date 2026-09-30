<?php
namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class WalletTransactionBuilder extends Builder
{
    public function update(array $values)
    {
        throw new \LogicException('Wallet ledger entries are immutable.');
    }

    public function delete()
    {
        throw new \LogicException('Wallet ledger entries are immutable.');
    }

    public function forceDelete()
    {
        throw new \LogicException('Wallet ledger entries are immutable.');
    }
}
