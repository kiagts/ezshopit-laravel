<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ProductDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'max:40'],
            'brand' => ['nullable'],
            'brand.*' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable'],
            'category.*' => ['nullable', 'string', 'max:255'],
            'price_min' => ['nullable', 'string', 'max:20'],
            'price_max' => ['nullable', 'string', 'max:20'],
            'in_stock' => ['nullable', 'boolean'],
            'attributes' => ['nullable', 'array', 'max:12'],
            'attributes.*' => ['nullable', 'array', 'max:20'],
            'attributes.*.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function discoveryFilters(): array
    {
        $validated = $this->validated();

        return [
            'q' => str($validated['q'] ?? '')->trim()->limit(120, '')->toString(),
            'sort' => $validated['sort'] ?? 'featured',
            'brand' => $this->cleanList($validated['brand'] ?? []),
            'category' => $this->cleanList($validated['category'] ?? []),
            'price_min' => $this->cleanScalar($validated['price_min'] ?? null),
            'price_max' => $this->cleanScalar($validated['price_max'] ?? null),
            'in_stock' => $this->boolean('in_stock'),
            'attributes' => $this->cleanAttributes($validated['attributes'] ?? []),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function cleanList(mixed $values): array
    {
        return collect(Arr::wrap($values))
            ->filter(fn (mixed $value): bool => is_string($value) && filled($value))
            ->map(fn (string $value): string => str($value)->lower()->trim()->toString())
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function cleanScalar(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str($value)->trim()->toString();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, array<int, string>>
     */
    private function cleanAttributes(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (mixed $values, mixed $code): bool => is_string($code) && is_array($values))
            ->mapWithKeys(fn (array $values, string $code): array => [
                str($code)->lower()->trim()->toString() => $this->cleanList($values),
            ])
            ->filter()
            ->take(12)
            ->all();
    }
}
