<?php
namespace App\Models;use App\Enums\WalletStatus;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Wallet extends Model{protected $fillable=['user_id','currency_code','status'];protected function casts():array{return ['status'=>WalletStatus::class];}public function user():BelongsTo{return $this->belongsTo(User::class);}public function transactions():HasMany{return $this->hasMany(WalletTransaction::class);}}
