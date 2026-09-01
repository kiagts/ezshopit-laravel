<?php

namespace App\Models;

use App\Enums\CommissionAdjustmentType;
use Database\Factories\CommissionAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionAdjustment extends Model
{
    /** @use HasFactory<CommissionAdjustmentFactory> */
    use HasFactory;

    protected $fillable = [
        'refund_id',
        'refund_allocation_id',
        'type',
        'commissionable_amount_delta',
        'commission_amount_delta',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommissionAdjustmentType::class,
            'commissionable_amount_delta' => 'integer',
            'commission_amount_delta' => 'integer',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(VendorOrderCommission::class, 'vendor_order_commission_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function refundAllocation(): BelongsTo
    {
        return $this->belongsTo(RefundAllocation::class);
    }
}
