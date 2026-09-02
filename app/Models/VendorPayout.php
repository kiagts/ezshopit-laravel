<?php

namespace App\Models;

use App\Enums\VendorPayoutMethodType;
use App\Enums\VendorPayoutStatus;
use Database\Factories\VendorPayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorPayout extends Model
{
    /** @use HasFactory<VendorPayoutFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = [
        'destination_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorPayoutStatus::class,
            'amount' => 'integer',
            'destination_snapshot' => 'encrypted:array',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processing_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'payout_number';
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(VendorPayoutMethod::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(VendorLedgerEntry::class);
    }

    public function payoutLedgerSourceKey(): string
    {
        return 'vendor-payout:'.$this->id.':payout';
    }

    public function maskedDestinationSnapshot(): string
    {
        $snapshot = $this->destination_snapshot ?: [];

        if (! blank($snapshot['iban'] ?? null)) {
            $clean = preg_replace('/\s+/', '', (string) $snapshot['iban']);

            return substr($clean, 0, 4).' **** **** '.substr($clean, -4);
        }

        foreach (['account_number', 'routing_number'] as $key) {
            if (! blank($snapshot[$key] ?? null)) {
                $clean = preg_replace('/\s+/', '', (string) $snapshot[$key]);

                return '****'.substr($clean, -4);
            }
        }

        return 'Destination snapshot';
    }

    public function methodLabel(): string
    {
        $type = $this->payout_method_type
            ? VendorPayoutMethodType::tryFrom($this->payout_method_type)
            : null;

        return $type?->label() ?? 'Payout method';
    }
}
