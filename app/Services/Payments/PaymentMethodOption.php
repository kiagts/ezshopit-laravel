<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;

class PaymentMethodOption
{
    public function __construct(
        public readonly PaymentMethod $method,
        public readonly string $label,
        public readonly string $description,
        public readonly string $provider,
    ) {
    }

    public function code(): string
    {
        return $this->method->value;
    }
}
