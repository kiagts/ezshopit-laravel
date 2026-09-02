<?php

namespace App\Models;

use App\Enums\RefundAllocationType;
use Database\Factories\RefundAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RefundAllocation extends Model
{
    /** @use HasFactory<RefundAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_order_id',
        'order_item_id',
        'type',
        'amount',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'type' => RefundAllocationType::class,
            'amount' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function commissionAdjustment(): HasOne
    {
        return $this->hasOne(CommissionAdjustment::class);
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(VendorLedgerEntry::class);
    }
}
