<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CmsFaqItem extends Model { protected $fillable=['cms_page_section_id','question','answer','sort_order','is_active']; protected function casts():array{return ['is_active'=>'boolean','sort_order'=>'integer'];} public function section():BelongsTo{return $this->belongsTo(CmsPageSection::class,'cms_page_section_id');} }
