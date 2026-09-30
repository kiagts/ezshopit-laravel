<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Market extends Model { protected $fillable=['name','code','country_code','default_currency_code','is_active','is_default','sort_order']; protected function casts():array{return ['is_active'=>'boolean','is_default'=>'boolean','sort_order'=>'integer'];} }
