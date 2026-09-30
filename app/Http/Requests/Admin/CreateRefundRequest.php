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
            'idempotency_key' => ['nullable','string','max:100'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1', 'max:30'],
            'allocations.*.type' => ['required', Rule::in(['item', 'shipping', 'adjustment'])],
            'allocations.*.amount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'allocations.*.quantity' => ['nullable', 'integer', 'min:1'],
            'allocations.*.order_item_id' => ['required_if:allocations.*.type,item', 'nullable', 'integer', 'exists:order_items,id'],
            'allocations.*.vendor_order_id' => ['required_if:allocations.*.type,shipping', 'nullable', 'integer', 'exists:vendor_orders,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('allocations', []))) return;
        $allocations = collect($this->input('allocations', []))
            ->map(function ($allocation): array {
                if (! is_array($allocation)) return [];
                try {
                    $raw = $allocation['amount'] ?? null;
                    $allocation['amount'] = is_array($raw) || is_object($raw) ? -1 : (Money::parseDecimal($raw) ?? 0);
                } catch (InvalidArgumentException) {
                    $allocation['amount'] = -1;
                }

                return $allocation;
            })
            ->all();

        $this->merge(['allocations' => $allocations]);
    }
}
