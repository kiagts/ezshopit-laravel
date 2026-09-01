<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Basic Information</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <x-input label="Name" name="name" :value="old('name', $category->name)" placeholder="Electronics" />
            <x-input label="Slug" name="slug" :value="old('slug', $category->slug)" placeholder="Leave blank to generate" />

            <x-select label="Parent Category" name="parent_id">
                <option value="">Root category</option>
                @foreach ($parentOptions as $option)
                    <option value="{{ $option['id'] }}" @selected((string) old('parent_id', $category->parent_id) === (string) $option['id'])>{{ $option['label'] }}</option>
                @endforeach
            </x-select>

            <x-select label="Product Attribute Set" name="product_attribute_set_id">
                <option value="">None</option>
                @foreach ($attributeSetOptions as $attributeSet)
                    <option value="{{ $attributeSet->id }}" @selected((string) old('product_attribute_set_id', $category->product_attribute_set_id) === (string) $attributeSet->id)>
                        {{ $attributeSet->name }}{{ $attributeSet->is_active ? '' : ' (inactive)' }}
                    </option>
                @endforeach
            </x-select>

            <x-input label="Sort Order" name="sort_order" type="number" min="0" :value="old('sort_order', $category->sort_order ?? 0)" />

            <div class="md:col-span-2">
                <x-textarea label="Description" name="description" placeholder="Optional internal or future storefront description">{{ old('description', $category->description) }}</x-textarea>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Visuals</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-neutral-800">Category Image</span>
                @if ($category->image_path)
                    <img src="{{ Storage::url($category->image_path) }}" alt="{{ $category->name }}" class="mb-3 h-24 w-36 rounded-md object-cover">
                @endif
                <input type="file" name="image" accept="image/*" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                @error('image')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-neutral-800">Category Icon</span>
                @if ($category->icon_path)
                    <img src="{{ Storage::url($category->icon_path) }}" alt="{{ $category->name }} icon" class="mb-3 h-16 w-16 rounded-md object-cover">
                @endif
                <input type="file" name="icon" accept="image/*" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                @error('icon')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Organization</h2>
        <label class="mt-6 inline-flex items-center gap-3 text-sm font-medium text-neutral-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
            Active category
        </label>
        <p class="mt-2 text-sm text-neutral-500">Inactive categories remain stored but future public catalog pages should hide them.</p>
        <p class="mt-2 text-sm text-neutral-500">Attribute sets are optional. Future product creation should prefer explicit sets on product-bearing leaf categories.</p>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">SEO</h2>
        <div class="mt-6 grid gap-5">
            <x-input label="SEO Title" name="seo_title" :value="old('seo_title', $category->seo_title)" placeholder="Optional; falls back to category name later" />
            <x-textarea label="SEO Description" name="seo_description" placeholder="Optional short search description">{{ old('seo_description', $category->seo_description) }}</x-textarea>
        </div>
    </x-card>

    <div class="flex flex-col gap-3 border-t border-neutral-200 pt-6 sm:flex-row sm:justify-end">
        <x-button :href="route('admin.categories.index')" variant="secondary">Cancel</x-button>
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
