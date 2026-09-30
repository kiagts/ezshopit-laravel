<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'product_attribute_set_id',
        'name',
        'slug',
        'description',
        'image_path',
        'icon_path',
        'is_active',
        'sort_order',
        'seo_title',
        'seo_description',
        'translations',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'translations' => 'array',
        ];
    }

    public function getLocalizedNameAttribute(): string { return data_get($this->translations, app()->getLocale().'.name', $this->name); }
    public function getLocalizedDescriptionAttribute(): ?string { return data_get($this->translations, app()->getLocale().'.description', $this->description); }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function productAttributeSet(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeSet::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->active();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }

    /**
     * @return Collection<int, int>
     */
    public function descendantIds(): Collection
    {
        $visited = collect();
        $frontier = collect([$this->id]);

        while ($frontier->isNotEmpty()) {
            $children = self::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->reject(fn (int $id): bool => $visited->contains($id));

            $visited = $visited->merge($children);
            $frontier = $children;
        }

        return $visited->values();
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function isLeaf(): bool
    {
        return ! $this->hasChildren();
    }

    public function isPubliclyUsable(): bool
    {
        if (! $this->is_active || ! $this->isLeaf()) {
            return false;
        }

        $parent = $this->parent;

        while ($parent) {
            if (! $parent->is_active) {
                return false;
            }

            $parent = $parent->parent;
        }

        return true;
    }
}
