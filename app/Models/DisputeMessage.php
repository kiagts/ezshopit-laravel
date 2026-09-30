<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    protected $fillable = ['dispute_id', 'author_id', 'author_role', 'body'];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function evidence()
    {
        return $this->hasMany(DisputeEvidence::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Dispute messages are immutable.'));
        static::deleting(fn () => throw new \LogicException('Dispute messages are immutable.'));
    }
}
