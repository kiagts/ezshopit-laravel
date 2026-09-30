<?php

namespace App\Models;

use App\Enums\VendorOrderStatus;
use Database\Factories\VendorOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorOrder extends Model
{
    /** @use HasFactory<VendorOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'shipping_method_id',
        'vendor_order_number',
        'status',
        'items_subtotal',
        'discount_total',
        'marketplace_discount_total',
        'vendor_discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'shipping_method_name',
        'shipping_method_code',
        'shipping_method_description',
        'shipping_min_delivery_days',
        'shipping_max_delivery_days',
        'confirmed_at',
        'processing_at',
        'ready_to_ship_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorOrderStatus::class,
            'items_subtotal' => 'integer',
            'discount_total' => 'integer',
            'marketplace_discount_total' => 'integer',
            'vendor_discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'shipping_min_delivery_days' => 'integer',
            'shipping_max_delivery_days' => 'integer',
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'ready_to_ship_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'vendor_order_number';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refundAllocations(): HasMany
    {
        return $this->hasMany(RefundAllocation::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(VendorOrderCommission::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class);
    }

    public function discountAllocations(): HasMany
    {
        return $this->hasMany(OrderDiscountAllocation::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function deliveryEstimate(): ?string
    {
        if ($this->shipping_min_delivery_days === null && $this->shipping_max_delivery_days === null) {
            return null;
        }

        $min = $this->shipping_min_delivery_days ?? $this->shipping_max_delivery_days;
        $max = $this->shipping_max_delivery_days ?? $this->shipping_min_delivery_days;

        return $min === $max
            ? "{$min} business ".str('day')->plural($min)
            : "{$min}-{$max} business days";
    }

    public function customerMerchandiseTotal(): int
    {
        return max(0, (int) $this->items_subtotal - (int) $this->discount_total);
    }

    public function sellerMerchandiseTotal(): int
    {
        return max(0, (int) $this->items_subtotal - (int) $this->vendor_discount_total);
    }
}
