<?php

namespace App\Models;

use Database\Factories\VendorOrderCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorOrderCommission extends Model
{
    /** @use HasFactory<VendorOrderCommissionFactory> */
    use HasFactory;

    protected $fillable = [
        'commission_rule_id',
        'rate_basis_points',
        'include_shipping',
        'original_items_amount',
        'original_shipping_amount',
        'original_commissionable_amount',
        'original_commission_amount',
        'currency',
        'recognized_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_basis_points' => 'integer',
            'include_shipping' => 'boolean',
            'original_items_amount' => 'integer',
            'original_shipping_amount' => 'integer',
            'original_commissionable_amount' => 'integer',
            'original_commission_amount' => 'integer',
            'recognized_at' => 'datetime',
        ];
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class);
    }
}
