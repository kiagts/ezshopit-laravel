<?php

namespace App\Http\Requests\Vendor\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    public function rules(): array
    {
        return [
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ];
    }
}
