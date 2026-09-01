<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'rate' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'include_shipping' => ['nullable', 'boolean'],
        ];
    }
}
