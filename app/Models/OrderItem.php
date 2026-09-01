<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_description',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'attributes_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'attributes_snapshot' => 'array',
        ];
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    public function refundAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RefundAllocation::class);
    }
}
