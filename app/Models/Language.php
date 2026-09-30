<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Language extends Model {protected $fillable=['code','name','native_name','direction','is_active','is_default','sort_order'];protected function casts():array{return ['is_active'=>'boolean','is_default'=>'boolean','sort_order'=>'integer'];}}
