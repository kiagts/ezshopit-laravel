<?php

namespace App\Models;

use App\Enums\VendorBusinessType;
use App\Enums\VendorStatus;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $fillable = [
        'business_name',
        'display_name',
        'slug',
        'email',
        'phone',
        'business_type',
        'description',
        'logo_path',
        'banner_path',
        'country',
        'city',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'website',
        'tax_number',
        'registration_number',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
            'business_type' => VendorBusinessType::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VendorStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', VendorStatus::Approved);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', VendorStatus::Rejected);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('display_name', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('user', function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }

    public function isApproved(): bool
    {
        return $this->status === VendorStatus::Approved;
    }
}
