<?php

namespace App\Models;

use App\Enums\PromotionFundingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDiscountAllocation extends Model
{
    protected $fillable = [
        'order_id',
        'vendor_order_id',
        'order_item_id',
        'promotion_id',
        'promotion_code',
        'promotion_name',
        'funding_source',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'funding_source' => PromotionFundingSource::class,
            'amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
