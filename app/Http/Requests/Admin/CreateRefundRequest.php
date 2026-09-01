<?php

namespace App\Http\Requests\Admin;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use Illuminate\Validation\Rule;

class CreateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.type' => ['required', Rule::in(['item', 'shipping', 'adjustment'])],
            'allocations.*.amount' => ['nullable', 'integer', 'min:0'],
            'allocations.*.quantity' => ['nullable', 'integer', 'min:1'],
            'allocations.*.order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'allocations.*.vendor_order_id' => ['nullable', 'integer', 'exists:vendor_orders,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allocations = collect($this->input('allocations', []))
            ->map(function (array $allocation): array {
                try {
                    $allocation['amount'] = Money::parseDecimal($allocation['amount'] ?? null) ?? 0;
                } catch (InvalidArgumentException) {
                    $allocation['amount'] = -1;
                }

                return $allocation;
            })
            ->all();

        $this->merge(['allocations' => $allocations]);
    }
}
