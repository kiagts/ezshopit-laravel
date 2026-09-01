<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $fillable = [
        'on_hand',
        'reserved',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function availableQuantity(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }

    public function isOutOfStock(): bool
    {
        return $this->availableQuantity() <= 0;
    }

    public function isLowStock(): bool
    {
        $available = $this->availableQuantity();

        return $available > 0 && $this->low_stock_threshold > 0 && $available <= $this->low_stock_threshold;
    }

    public function isInStock(): bool
    {
        return $this->availableQuantity() > 0 && ! $this->isLowStock();
    }

    public function stockStatusLabel(): string
    {
        if ($this->isOutOfStock()) {
            return 'Out of Stock';
        }

        return $this->isLowStock() ? 'Low Stock' : 'In Stock';
    }

    public function stockStatusTone(): string
    {
        if ($this->isOutOfStock()) {
            return 'danger';
        }

        return $this->isLowStock() ? 'warning' : 'success';
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereRaw('(on_hand - reserved) > low_stock_threshold');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('(on_hand - reserved) > 0')->whereRaw('(on_hand - reserved) <= low_stock_threshold')->where('low_stock_threshold', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->whereRaw('(on_hand - reserved) <= 0');
    }
}
