<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnHistory extends Model
{
    protected $fillable = ['return_id', 'actor_id', 'actor_role', 'from_status', 'to_status', 'note'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Return history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Return history is append-only.'));
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
