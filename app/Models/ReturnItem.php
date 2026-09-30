<?php

namespace App\Models;

use App\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = ['return_id', 'order_item_id', 'quantity', 'condition', 'restock', 'inspected_quantity', 'inspection_note', 'resolution', 'inventory_movement_id', 'restocked_at'];

    protected $casts = ['restock' => 'boolean', 'quantity' => 'integer', 'inspected_quantity' => 'integer', 'condition' => ReturnCondition::class, 'restocked_at' => 'datetime'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
