<?php

namespace App\Models;

use App\Enums\ShippingMethodType;
use App\Support\Countries;
use App\Support\Money;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'flat_rate',
        'free_shipping_threshold',
        'min_delivery_days',
        'max_delivery_days',
        'ships_to_all_supported_countries',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'flat_rate' => 'integer',
            'free_shipping_threshold' => 'integer',
            'min_delivery_days' => 'integer',
            'max_delivery_days' => 'integer',
            'ships_to_all_supported_countries' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function countries(): HasMany
    {
        return $this->hasMany(ShippingMethodCountry::class);
    }

    public function supportsCountry(string $countryCode): bool
    {
        if (! in_array(strtoupper($countryCode), Countries::codes(), true)) {
            return false;
        }

        return $this->ships_to_all_supported_countries
            || $this->countries->contains('country_code', strtoupper($countryCode));
    }

    public function deliveryEstimate(): ?string
    {
        if ($this->min_delivery_days === null && $this->max_delivery_days === null) {
            return null;
        }

        $min = $this->min_delivery_days ?? $this->max_delivery_days;
        $max = $this->max_delivery_days ?? $this->min_delivery_days;

        return $min === $max
            ? "{$min} business ".str('day')->plural($min)
            : "{$min}-{$max} business days";
    }

    public function coverageLabel(): string
    {
        if ($this->ships_to_all_supported_countries) {
            return 'All supported countries';
        }

        return $this->countries
            ->pluck('country_code')
            ->map(fn (string $code): string => Countries::name($code))
            ->sort()
            ->implode(', ') ?: 'No countries selected';
    }

    public function thresholdLabel(): string
    {
        return $this->free_shipping_threshold === null
            ? '-'
            : Money::format($this->free_shipping_threshold);
    }
}
