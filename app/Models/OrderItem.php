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
        'tax_total',
        'is_returnable',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_description',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'discount_total',
        'marketplace_discount_total',
        'vendor_discount_total',
        'net_line_total',
        'attributes_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'tax_total' => 'integer',
            'is_returnable' => 'boolean',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'discount_total' => 'integer',
            'marketplace_discount_total' => 'integer',
            'vendor_discount_total' => 'integer',
            'net_line_total' => 'integer',
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

    public function discountAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderDiscountAllocation::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    public function customerLineTotal(): int
    {
        if ((int) $this->discount_total === 0) {
            return (int) $this->line_total;
        }

        return $this->net_line_total ?? max(0, (int) $this->line_total - (int) $this->discount_total);
    }

    public function sellerLineTotal(): int
    {
        return max(0, (int) $this->line_total - (int) $this->vendor_discount_total);
    }
}
