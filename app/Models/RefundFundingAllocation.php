<?php

namespace App\Models;

use App\Enums\RefundFundingSource;
use App\Enums\RefundFundingStatus;
use Illuminate\Database\Eloquent\Model;

class RefundFundingAllocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer', 'source_type' => RefundFundingSource::class, 'status' => RefundFundingStatus::class];

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    protected static function booted(): void
    {
        static::updating(function ($row) {
            if ($row->isDirty(['refund_id', 'source_type', 'source_id', 'amount', 'currency', 'idempotency_key'])) {
                throw new \LogicException('Refund funding instructions are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Refund funding instructions cannot be deleted.'));
    }
}
