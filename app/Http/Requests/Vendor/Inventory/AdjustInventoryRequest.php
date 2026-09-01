<?php

namespace App\Http\Requests\Vendor\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    public function rules(): array
    {
        return [
            'on_hand' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
