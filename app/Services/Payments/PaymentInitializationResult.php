<?php

namespace App\Services\Payments;

class PaymentInitializationResult
{
    public function __construct(
        public readonly bool $completed,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $instructions = null,
        public readonly bool $requiresClientConfirmation = false,
        public readonly ?string $clientSecret = null,
        public readonly ?string $providerReference = null,
    ) {
    }

    public static function pending(?string $instructions = null): self
    {
        return new self(completed: false, instructions: $instructions);
    }

    public static function requiresClientConfirmation(string $clientSecret, string $providerReference): self
    {
        return new self(
            completed: false,
            requiresClientConfirmation: true,
            clientSecret: $clientSecret,
            providerReference: $providerReference,
        );
    }
}
