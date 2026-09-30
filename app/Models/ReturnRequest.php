<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    public function refunds(): HasMany { return $this->hasMany(Refund::class, 'return_request_id'); }
    protected $table = 'returns';

    protected $fillable = ['return_number', 'order_id', 'vendor_order_id', 'user_id', 'status', 'reason', 'notes'];

    protected $casts = ['status' => ReturnStatus::class];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ReturnHistory::class, 'return_id')->orderBy('id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReturnEvidence::class, 'return_id');
    }
}
