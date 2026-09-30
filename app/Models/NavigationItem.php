<?php
namespace App\Models;
use App\Enums\NavigationLinkType; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class NavigationItem extends Model { protected $fillable=['navigation_menu_id','label','link_type','reference_id','url','sort_order','is_active','open_in_new_tab']; protected function casts():array{return ['link_type'=>NavigationLinkType::class,'is_active'=>'boolean','open_in_new_tab'=>'boolean','sort_order'=>'integer'];} public function menu():BelongsTo{return $this->belongsTo(NavigationMenu::class,'navigation_menu_id');} }
