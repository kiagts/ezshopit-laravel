<?php

namespace App\Http\Requests\Vendor\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class IncreaseInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
