<?php
namespace App\Models;
use App\Enums\NavigationMenuLocation; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class NavigationMenu extends Model { protected $fillable=['name','location','is_active']; protected function casts():array{return ['location'=>NavigationMenuLocation::class,'is_active'=>'boolean'];} public function items():HasMany{return $this->hasMany(NavigationItem::class)->orderBy('sort_order')->orderBy('id');} }
