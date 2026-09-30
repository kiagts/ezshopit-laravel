<?php
namespace App\Models;use App\Enums\WalletTransactionType;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WalletTransaction extends Model
{
    protected $fillable = ['wallet_id','type','amount','currency_code','reference_type','reference_id','description','idempotency_key','actor_id','metadata'];
    protected function casts(): array { return ['type'=>WalletTransactionType::class,'amount'=>'integer','metadata'=>'array','reference_id'=>'integer']; }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function newEloquentBuilder($query): \App\Models\Builders\WalletTransactionBuilder
    {
        return new \App\Models\Builders\WalletTransactionBuilder($query);
    }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Wallet ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Wallet ledger entries are immutable.'));
        static::creating(function ($entry) {
            if ($entry->amount <= 0 || $entry->currency_code !== $entry->wallet()->value('currency_code')) {
                throw new \InvalidArgumentException('Invalid wallet entry amount or currency.');
            }
        });
    }
}
