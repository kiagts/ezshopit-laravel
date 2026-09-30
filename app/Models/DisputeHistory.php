<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeHistory extends Model
{
    protected $guarded = ['id'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Dispute history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Dispute history is immutable.'));
    }
}
