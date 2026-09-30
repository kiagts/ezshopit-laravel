<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderCancellationSource;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_address_id',
        'idempotency_key',
        'status',
        'payment_status',
        'currency',
        'items_subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
          'wallet_applied_total',
          'gift_card_applied_total',
          'gift_card_last_four',
        'grand_total',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_phone',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_city',
        'shipping_state_region',
        'shipping_postal_code',
        'shipping_country_code',
        'placed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_source',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'items_subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
              'wallet_applied_total' => 'integer',
              'gift_card_applied_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_source' => OrderCancellationSource::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'reference_id')->where('reference_type', 'order');
    }

    public function discountAllocations(): HasMany
    {
        return $this->hasMany(OrderDiscountAllocation::class);
    }

    public function promotionRedemption(): HasOne
    {
        return $this->hasOne(PromotionRedemption::class);
    }

    public function refunds(): HasManyThrough
    {
        return $this->hasManyThrough(Refund::class, Payment::class);
    }

    public function itemCount(): int
    {
        if ($this->relationLoaded('vendorOrders')) {
            return $this->vendorOrders->sum(fn (VendorOrder $vendorOrder): int => $vendorOrder->items->sum('quantity'));
        }

        return (int) OrderItem::query()
            ->whereHas('vendorOrder', fn (Builder $query) => $query->whereKey($this->id))
            ->sum('quantity');
    }

    public function sellerCount(): int
    {
        return $this->relationLoaded('vendorOrders')
            ? $this->vendorOrders->count()
            : $this->vendorOrders()->count();
    }

    public function shippingRecipientName(): string
    {
        return trim($this->shipping_first_name.' '.$this->shipping_last_name);
    }

    public function merchandiseTotalAfterDiscount(): int
    {
        return max(0, (int) $this->items_subtotal - (int) $this->discount_total);
    }
}
