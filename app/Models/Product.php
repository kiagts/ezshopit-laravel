<?php

namespace App\Models;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\VendorStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Product $product): void {
            if (! $product->isForceDeleting()) {
                $product->variants()->delete();
            }
        });
    }

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'type',
        'status',
        'short_description',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'approval_status' => ProductApprovalStatus::class,
            'is_featured' => 'boolean',
            'rating_sum' => 'integer',
            'rating_count' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->ordered();
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->ordered();
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function galleryImages(): HasMany
    {
        return $this->images()->general();
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->whereNull('product_variant_id')
            ->where('is_primary', true)
            ->oldest('sort_order')
            ->oldest('id');
    }

    public function firstGalleryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->whereNull('product_variant_id')
            ->oldest('sort_order')
            ->oldest('id');
    }

    public function scopeForVendor(Builder $query, Vendor $vendor): Builder
    {
        return $query->where('vendor_id', $vendor->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Draft);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', ProductApprovalStatus::Approved);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->active()
            ->approved()
            ->whereHas('vendor', fn (Builder $query) => $query->where('status', VendorStatus::Approved))
            ->whereHas('category', function (Builder $query): void {
                $query->active()
                    ->whereDoesntHave('parent', fn (Builder $query) => $query->where('is_active', false))
                    ->whereDoesntHave('parent.parent', fn (Builder $query) => $query->where('is_active', false))
                    ->whereDoesntHave('parent.parent.parent', fn (Builder $query) => $query->where('is_active', false));
            })
            ->whereHas('variants', fn (Builder $query) => $query->active());
    }

    public function isSimple(): bool
    {
        return $this->type === ProductType::Simple;
    }

    public function isVariant(): bool
    {
        return $this->type === ProductType::Variant;
    }

    public function displayImage(): ?ProductImage
    {
        return $this->primaryImage ?: $this->firstGalleryImage;
    }

    public function activeVariantsForDisplay()
    {
        return $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)
            : $this->variants()->active()->get();
    }

    public function storefrontPrice(): array
    {
        $variants = $this->activeVariantsForDisplay();
        $prices = $variants->map(fn (ProductVariant $variant): array => [
            'base' => $variant->base_price,
            'effective' => $variant->sale_price ?? $variant->base_price,
            'sale' => $variant->sale_price,
        ])->values();

        if ($prices->isEmpty()) {
            return ['effective' => null, 'base' => null, 'sale' => null, 'from' => false, 'discount' => null];
        }

        $lowest = $prices->sortBy('effective')->first();
        $effectiveValues = $prices->pluck('effective')->unique();
        $discount = $lowest['sale'] && $lowest['base'] > 0
            ? (int) round((($lowest['base'] - $lowest['sale']) / $lowest['base']) * 100)
            : null;

        return [
            'effective' => $lowest['effective'],
            'base' => $lowest['base'],
            'sale' => $lowest['sale'],
            'from' => $effectiveValues->count() > 1,
            'discount' => $discount && $discount > 0 ? $discount : null,
        ];
    }

    public function publicStockState(): string
    {
        $inventories = $this->activeVariantsForDisplay()->pluck('inventory')->filter();

        if ($inventories->isEmpty() || $inventories->every(fn (Inventory $inventory): bool => $inventory->isOutOfStock())) {
            return 'out_of_stock';
        }

        if ($inventories->contains(fn (Inventory $inventory): bool => $inventory->isLowStock())) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function averageRating(): ?float
    {
        if ($this->rating_count <= 0) {
            return null;
        }

        return round($this->rating_sum / $this->rating_count, 1);
    }

    public function approvedReviewCount(): int
    {
        return (int) $this->rating_count;
    }
}
