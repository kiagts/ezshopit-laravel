<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Currency extends Model { protected $fillable=['code','name','symbol','minor_unit','is_active','sort_order']; protected function casts():array{return ['minor_unit'=>'integer','is_active'=>'boolean','sort_order'=>'integer'];} }
