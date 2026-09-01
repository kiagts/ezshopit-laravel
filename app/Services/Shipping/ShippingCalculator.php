<?php

namespace App\Services\Shipping;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use RuntimeException;

class ShippingCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(ShippingMethod $method, int $vendorSubtotal): array
    {
        if ($method->type !== ShippingMethodType::FlatRate) {
            throw new RuntimeException('Unsupported shipping method type.');
        }

        $baseRate = $method->flat_rate;
        $isFree = $method->free_shipping_threshold !== null
            && $vendorSubtotal >= $method->free_shipping_threshold;
        $effectiveRate = $isFree ? 0 : $baseRate;

        return [
            'base_rate' => $baseRate,
            'effective_rate' => $effectiveRate,
            'is_free' => $isFree,
            'delivery_estimate' => $method->deliveryEstimate(),
        ];
    }
}
