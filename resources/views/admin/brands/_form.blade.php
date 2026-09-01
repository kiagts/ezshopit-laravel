<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Brand Information</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <x-input label="Name" name="name" :value="old('name', $brand->name)" placeholder="Apple" />
            <x-input label="Slug" name="slug" :value="old('slug', $brand->slug)" placeholder="Leave blank to generate" />

            <div class="md:col-span-2">
                <x-textarea label="Description" name="description" placeholder="Optional brand description for future landing pages">{{ old('description', $brand->description) }}</x-textarea>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Branding</h2>
        <label class="mt-6 block">
            <span class="mb-2 block text-sm font-medium text-neutral-800">Logo</span>
            @if ($brand->logo_path)
                <img src="{{ Storage::url($brand->logo_path) }}" alt="{{ $brand->name }} logo" class="mb-3 h-20 w-20 rounded-md object-contain">
            @endif
            <input type="file" name="logo" accept="image/*" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <span class="mt-2 block text-xs text-neutral-500">PNG, JPG, WebP, or SVG up to 2MB. Existing logo is preserved unless replaced.</span>
            @error('logo')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Organization</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <label class="inline-flex items-center gap-3 text-sm font-medium text-neutral-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->exists ? $brand->is_active : true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                Active brand
            </label>
            <x-input label="Sort Order" name="sort_order" type="number" min="0" :value="old('sort_order', $brand->sort_order ?? 0)" />
        </div>
        <p class="mt-2 text-sm text-neutral-500">Inactive brands remain stored and can be excluded from future public catalog queries.</p>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">SEO</h2>
        <div class="mt-6 grid gap-5">
            <x-input label="SEO Title" name="seo_title" :value="old('seo_title', $brand->seo_title)" placeholder="Optional; falls back to brand name later" />
            <x-textarea label="SEO Description" name="seo_description" placeholder="Optional short search description">{{ old('seo_description', $brand->seo_description) }}</x-textarea>
        </div>
    </x-card>

    <div class="flex flex-col gap-3 border-t border-neutral-200 pt-6 sm:flex-row sm:justify-end">
        <x-button :href="route('admin.brands.index')" variant="secondary">Cancel</x-button>
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
