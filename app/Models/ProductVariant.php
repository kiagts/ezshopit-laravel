<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (ProductVariant $variant): void {
            $variant->inventory()->firstOrCreate([], [
                'on_hand' => 0,
                'reserved' => 0,
                'low_stock_threshold' => (int) config('marketplace.inventory.default_low_stock_threshold', 5),
            ]);
        });
    }

    protected $fillable = [
        'sku',
        'name',
        'base_price',
        'sale_price',
        'currency_code',
        'is_active',
        'is_default',
        'combination_key',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'sale_price' => 'integer',
            'currency_code' => 'string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_variant_attribute_values')
            ->withPivot('product_attribute_id')
            ->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->ordered();
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->oldest('sort_order')
            ->oldest('id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        if ($this->is_default || $this->combination_key === 'default') {
            return 'Default';
        }

        $values = $this->relationLoaded('attributeValues')
            ? $this->attributeValues
            : $this->attributeValues()->get();

        return $values->pluck('value')->filter()->implode(' / ') ?: 'Variant';
    }

    public function availableQuantity(): int
    {
        return $this->inventory?->availableQuantity() ?? 0;
    }

    public function isInStock(): bool
    {
        return $this->inventory?->isInStock() ?? false;
    }

    public function isLowStock(): bool
    {
        return $this->inventory?->isLowStock() ?? false;
    }

    public function isOutOfStock(): bool
    {
        return $this->inventory?->isOutOfStock() ?? true;
    }

    public function storefrontPrice(): array
    {
        $target = function_exists('request') && request()->hasSession() ? app(\App\Services\CurrencyContext::class)->current(request())->code : ($this->currency_code ?: \App\Support\Money::currencyCode());
        $convert = function (?int $amount) use ($target): ?int { if ($amount === null) return null; $source = $this->currency_code ?: \App\Support\Money::currencyCode(); return $source === $target ? $amount : app(\App\Services\CurrencyConverter::class)->convert($amount, $source, $target); };
        $baseAmount = $convert($this->base_price); $saleAmount = $convert($this->sale_price);
        $discount = $saleAmount && $baseAmount > 0
            ? (int) round((($baseAmount - $saleAmount) / $baseAmount) * 100)
            : null;

        return [
            'effective' => $saleAmount ?? $baseAmount,
            'base' => $baseAmount,
            'sale' => $saleAmount,
            'from' => false,
            'discount' => $discount && $discount > 0 ? $discount : null,
        ];
    }

    public function publicStockState(): string
    {
        if (! $this->inventory || $this->inventory->isOutOfStock()) {
            return 'out_of_stock';
        }

        return $this->inventory->isLowStock() ? 'low_stock' : 'in_stock';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('sku');
    }
}
