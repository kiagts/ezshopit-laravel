<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use Illuminate\Support\Collection;

class AvailablePaymentMethods
{
    /**
     * @return Collection<int, PaymentMethodOption>
     */
    public function all(): Collection
    {
        return collect(config('payments.methods', []))
            ->filter(fn (array $config): bool => (bool) ($config['enabled'] ?? false))
            ->map(function (array $config, string $code): ?PaymentMethodOption {
                $method = PaymentMethod::tryFrom($code);

                if (! $method) {
                    return null;
                }

                if (($config['provider'] ?? null) === 'stripe' && ! $this->stripeIsAvailable()) {
                    return null;
                }

                return new PaymentMethodOption(
                    method: $method,
                    label: (string) ($config['label'] ?? $method->label()),
                    description: (string) ($config['description'] ?? ''),
                    provider: (string) ($config['provider'] ?? $method->value),
                );
            })
            ->filter()
            ->values();
    }

    public function find(string $method): ?PaymentMethodOption
    {
        return $this->all()->first(fn (PaymentMethodOption $option): bool => $option->code() === $method);
    }

    public function default(): ?PaymentMethodOption
    {
        return $this->all()->first();
    }

    private function stripeIsAvailable(): bool
    {
        $currency = strtolower((string) config('marketplace.currency.code', 'USD'));

        return (bool) config('payments.stripe.enabled')
            && filled(config('payments.stripe.public_key'))
            && filled(config('payments.stripe.secret_key'))
            && in_array($currency, config('payments.stripe.supported_currencies', []), true);
    }
}
