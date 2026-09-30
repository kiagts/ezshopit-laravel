<?php

namespace App\Models;

use App\Enums\HomepageSectionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageSection extends Model
{
    protected $fillable = [
        'key',
        'type',
        'name',
        'title',
        'subtitle',
        'is_enabled',
        'sort_order',
        'starts_at',
        'ends_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => HomepageSectionType::class,
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomepageSectionItem::class)->ordered();
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(HomepageSectionContentItem::class)->ordered();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeVisible(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_enabled', true)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public function derivedStatus(?\DateTimeInterface $at = null): string
    {
        $at ??= now();

        if (! $this->is_enabled) {
            return 'Disabled';
        }

        if ($this->starts_at && $this->starts_at->greaterThan($at)) {
            return 'Scheduled';
        }

        if ($this->ends_at && $this->ends_at->lessThan($at)) {
            return 'Expired';
        }

        return 'Active';
    }
}
