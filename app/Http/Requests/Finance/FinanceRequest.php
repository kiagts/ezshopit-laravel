<?php

namespace App\Http\Requests\Finance;

use App\Enums\VendorLedgerEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isVendor();
    }

    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'type' => ['nullable', Rule::enum(VendorLedgerEntryType::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'reference' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
