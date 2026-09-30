<?php
namespace App\Models;
use App\Enums\CmsPageSectionType; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class CmsPageSection extends Model { protected $fillable=['cms_page_id','type','title','content','settings','sort_order']; protected function casts():array{return ['type'=>CmsPageSectionType::class,'settings'=>'array','sort_order'=>'integer'];} public function page():BelongsTo{return $this->belongsTo(CmsPage::class,'cms_page_id');} public function faqItems():HasMany{return $this->hasMany(CmsFaqItem::class)->orderBy('sort_order')->orderBy('id');} }
