<?php

namespace App\Models;

use App\Enums\VendorPayoutMethodType;
use Database\Factories\VendorPayoutMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayoutMethod extends Model
{
    /** @use HasFactory<VendorPayoutMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'label',
        'account_holder_name',
        'bank_name',
        'account_number',
        'iban',
        'swift_code',
        'routing_number',
        'country_code',
        'is_default',
    ];

    protected $hidden = [
        'account_number',
        'iban',
        'routing_number',
    ];

    protected function casts(): array
    {
        return [
            'type' => VendorPayoutMethodType::class,
            'account_number' => 'encrypted',
            'iban' => 'encrypted',
            'routing_number' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function maskedAccountNumber(): ?string
    {
        return $this->mask($this->account_number);
    }

    public function maskedIban(): ?string
    {
        if (blank($this->iban)) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', (string) $this->iban);
        $prefix = substr($clean, 0, 4);
        $suffix = substr($clean, -4);

        return "{$prefix} **** **** {$suffix}";
    }

    public function maskedRoutingNumber(): ?string
    {
        return $this->mask($this->routing_number);
    }

    public function maskedDestination(): string
    {
        return $this->maskedIban()
            ?: $this->maskedAccountNumber()
            ?: $this->maskedRoutingNumber()
            ?: 'Destination on file';
    }

    public function destinationSnapshot(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->label,
            'account_holder_name' => $this->account_holder_name,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'iban' => $this->iban,
            'swift_code' => $this->swift_code,
            'routing_number' => $this->routing_number,
            'country_code' => $this->country_code,
        ];
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', $value);
        $suffix = substr($clean, -4);

        return '****'.$suffix;
    }
}
