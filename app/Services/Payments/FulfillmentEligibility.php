<?php

namespace App\Services\Payments;

class FulfillmentEligibility
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $message = null,
    ) {
    }
}
