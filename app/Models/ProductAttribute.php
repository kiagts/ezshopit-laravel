<?php

namespace App\Models;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use Database\Factories\ProductAttributeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAttribute extends Model
{
    /** @use HasFactory<ProductAttributeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'input_type',
        'is_filterable',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductAttributeType::class,
            'input_type' => ProductAttributeInputType::class,
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->ordered();
    }

    public function activeValues(): HasMany
    {
        return $this->values()->active();
    }

    public function attributeSets(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeSet::class, 'product_attribute_set_attributes')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
                ->orWhere('code', 'like', "%{$search}%");
        });
    }

    public function usesPredefinedValues(): bool
    {
        return $this->input_type->usesPredefinedValues();
    }

    public function isVariant(): bool
    {
        return $this->type === ProductAttributeType::Variant;
    }
}
