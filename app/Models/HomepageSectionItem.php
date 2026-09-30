<?php

namespace App\Models;

use App\Enums\HomepageSectionItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSectionItem extends Model
{
    protected $fillable = [
        'item_type',
        'item_id',
        'sort_order',
        'custom_title',
        'custom_subtitle',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => HomepageSectionItemType::class,
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
