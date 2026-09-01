<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSpecification extends Model
{
    protected $fillable = [
        'product_attribute_id',
        'text_value',
        'number_value',
        'boolean_value',
    ];

    protected function casts(): array
    {
        return [
            'number_value' => 'decimal:4',
            'boolean_value' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_specification_values')
            ->withTimestamps();
    }
}
