<?php

namespace App\Http\Requests\Admin\Homepage;

use App\Enums\HomepageSectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreHomepageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(HomepageSectionType::class)],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:homepage_sections,key'],
            'name' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'settings' => ['nullable', 'array'],
            'item_ids' => ['nullable', 'array', 'max:24'],
            'item_ids.*' => ['integer'],
            'content_items' => ['nullable', 'array', 'max:24'],
            'content_items.*.title' => ['nullable', 'string', 'max:160'],
            'content_items.*.body' => ['nullable', 'string', 'max:500'],
            'content_items.*.image_path' => ['nullable', 'string', 'max:255'],
            'content_items.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'content_items.*.mobile_image_path' => ['nullable', 'string', 'max:255'],
            'content_items.*.mobile_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'content_items.*.image_alt' => ['nullable', 'string', 'max:120'],
            'content_items.*.icon_key' => ['nullable', 'string', 'max:40'],
            'content_items.*.cta_label' => ['nullable', 'string', 'max:60'],
            'content_items.*.cta_url' => ['nullable', 'string', 'max:255'],
            'content_items.*.settings' => ['nullable', 'array:group,layout,eyebrow'],
            'content_items.*.settings.group' => ['nullable', 'integer', 'between:0,2'],
            'content_items.*.settings.layout' => ['nullable', 'string', 'max:40'],
            'content_items.*.settings.eyebrow' => ['nullable', 'string', 'max:80'],
        ];
    }
}
