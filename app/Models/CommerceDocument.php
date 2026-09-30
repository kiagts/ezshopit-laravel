<?php

namespace App\Models;

use App\Enums\CommerceDocumentType;
use App\Models\Builders\CommerceDocumentBuilder;
use Illuminate\Database\Eloquent\Model;

class CommerceDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['document_type' => CommerceDocumentType::class, 'issuer_snapshot' => 'array', 'recipient_snapshot' => 'array', 'content' => 'array', 'minor_unit' => 'integer', 'issued_at' => 'datetime'];
    }

    public function newEloquentBuilder($query)
    {
        return new CommerceDocumentBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Issued documents are immutable.'));
        static::deleting(fn () => throw new \LogicException('Issued documents cannot be deleted.'));
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder()
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }
}
