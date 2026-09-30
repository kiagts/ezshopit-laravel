<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $fillable = [
        'return_request_id', 'vendor_order_id', 'idempotency_key', 'request_hash', 'economics_posted_at', 'processed_by',
        'refund_number',
        'order_id',
        'payment_id',
        'status',
        'amount',
        'currency',
        'reason',
        'notes',
        'provider',
        'provider_reference',
        'requested_by',
        'requested_at',
        'processed_at',
        'failed_at',
        'cancelled_at',
        'failure_code',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'economics_posted_at' => 'datetime',
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'refund_number';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RefundAllocation::class);
    }

    public function fundingAllocations(): HasMany { return $this->hasMany(RefundFundingAllocation::class); }
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function economicsRecognized(): bool { return $this->economics_posted_at !== null || $this->status === RefundStatus::Succeeded; }
}
