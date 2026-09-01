<?php

namespace App\Services\Commissions;

use App\Models\VendorOrderCommission;
use InvalidArgumentException;

class CommissionCalculator
{
    public function calculate(int $commissionableAmount, int $rateBasisPoints): int
    {
        if ($commissionableAmount <= 0 || $rateBasisPoints <= 0) {
            return 0;
        }

        return intdiv(($commissionableAmount * $rateBasisPoints) + 5000, 10000);
    }

    public function commissionableAmount(int $itemsAmount, int $shippingAmount, bool $includeShipping): int
    {
        return $itemsAmount + ($includeShipping ? $shippingAmount : 0);
    }

    public function parseBasisPoints(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Enter a percentage rate.');
        }

        $input = trim((string) $value);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $input)) {
            throw new InvalidArgumentException('Enter a valid percentage with up to two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $input, 2), 2, '');
        $basisPoints = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        if ($basisPoints > 10000) {
            throw new InvalidArgumentException('Commission rate cannot exceed 100%.');
        }

        return $basisPoints;
    }

    public function formatRate(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 2).'%';
    }

    public function netCommissionableAmount(VendorOrderCommission $commission): int
    {
        $delta = (int) $commission->adjustments->sum('commissionable_amount_delta');

        return max(0, $commission->original_commissionable_amount + $delta);
    }

    public function netCommissionAmount(VendorOrderCommission $commission): int
    {
        $delta = (int) $commission->adjustments->sum('commission_amount_delta');

        return max(0, $commission->original_commission_amount + $delta);
    }

    public function netAfterCommission(VendorOrderCommission $commission): int
    {
        $sellerValue = $commission->vendorOrder
            ? max(0, $commission->vendorOrder->grand_total + (int) $commission->adjustments->sum('commissionable_amount_delta'))
            : $this->netCommissionableAmount($commission);

        return max(0, $sellerValue - $this->netCommissionAmount($commission));
    }
}
