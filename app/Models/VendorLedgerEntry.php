<?php

namespace App\Models;

use App\Enums\VendorLedgerEntryType;
use Database\Factories\VendorLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorLedgerEntry extends Model
{
    /** @use HasFactory<VendorLedgerEntryFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    public function newEloquentBuilder($query): \App\Models\Builders\VendorLedgerEntryBuilder
    {
        return new \App\Models\Builders\VendorLedgerEntryBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Vendor ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Vendor ledger entries are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'type' => VendorLedgerEntryType::class,
            'amount' => 'integer',
            'available_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function refundAllocation(): BelongsTo
    {
        return $this->belongsTo(RefundAllocation::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(VendorOrderCommission::class, 'vendor_order_commission_id');
    }

    public function commissionAdjustment(): BelongsTo
    {
        return $this->belongsTo(CommissionAdjustment::class);
    }

    public function vendorPayout(): BelongsTo
    {
        return $this->belongsTo(VendorPayout::class);
    }

    public function isAvailable(): bool
    {
        return $this->available_at === null || $this->available_at->isPast();
    }
}

