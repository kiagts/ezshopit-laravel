<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentProviderManager
{
    /**
     * @param iterable<int, PaymentProvider> $providers
     */
    public function __construct(private readonly iterable $providers = [])
    {
    }

    public function resolve(string $provider): PaymentProvider
    {
        foreach ($this->configuredProviders() as $candidate) {
            if ($candidate->code() === $provider) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException("Payment provider [{$provider}] is not available.");
    }

    /**
     * @return array<int, PaymentProvider>
     */
    private function configuredProviders(): array
    {
        $providers = iterator_to_array($this->providers);

        if ($providers === []) {
            $providers[] = app(CashOnDeliveryPaymentProvider::class);
            $providers[] = app(StripePaymentProvider::class);
        }

        return $providers;
    }
}
