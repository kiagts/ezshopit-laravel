@php
    use App\Support\Money;

    $selectedCountries = collect(old('countries', $method->countries?->pluck('country_code')->all() ?? []))
        ->map(fn ($code) => strtoupper((string) $code))
        ->all();
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if (($methodOverride ?? 'POST') !== 'POST')
        @method($methodOverride)
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input label="Name" name="name" placeholder="Standard Delivery" value="{{ old('name', $method->name) }}" />
        <x-input label="Code" name="code" placeholder="standard" value="{{ old('code', $method->code) }}" />
    </div>

    <x-textarea label="Description" name="description" rows="3" placeholder="Tracked flat-rate delivery.">{{ old('description', $method->description) }}</x-textarea>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input label="Flat rate ({{ Money::currencyCode() }})" name="flat_rate" placeholder="5.00" value="{{ old('flat_rate', Money::input($method->flat_rate)) }}" />
        <x-input label="Free shipping threshold ({{ Money::currencyCode() }})" name="free_shipping_threshold" placeholder="100.00" value="{{ old('free_shipping_threshold', Money::input($method->free_shipping_threshold)) }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-input label="Minimum delivery days" name="min_delivery_days" type="number" min="0" value="{{ old('min_delivery_days', $method->min_delivery_days) }}" />
        <x-input label="Maximum delivery days" name="max_delivery_days" type="number" min="0" value="{{ old('max_delivery_days', $method->max_delivery_days) }}" />
        <x-input label="Sort order" name="sort_order" type="number" value="{{ old('sort_order', $method->sort_order ?? 0) }}" />
    </div>

    <input type="hidden" name="ships_to_all_supported_countries" value="0">
    <label class="flex items-start gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm">
        <input type="checkbox" name="ships_to_all_supported_countries" value="1" class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600" @checked(old('ships_to_all_supported_countries', $method->ships_to_all_supported_countries ?? true))>
        <span>
            <span class="block font-medium text-neutral-950">Ships to all supported countries</span>
            <span class="mt-1 block leading-6 text-neutral-600">Disable this only when the method is limited to specific marketplace countries.</span>
        </span>
    </label>

    <fieldset class="rounded-lg border border-neutral-200 bg-white p-4">
        <legend class="px-1 text-sm font-semibold text-neutral-950">Country coverage</legend>
        @error('countries')
            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($countries as $code => $name)
                <label class="flex items-center gap-2 rounded-md border border-neutral-200 px-3 py-2 text-sm">
                    <input type="checkbox" name="countries[]" value="{{ $code }}" class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600" @checked(in_array($code, $selectedCountries, true))>
                    <span>{{ $name }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-3 text-sm font-medium text-neutral-950">
        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600" @checked(old('is_active', $method->is_active ?? true))>
        Active
    </label>

    <div class="flex flex-wrap items-center gap-3">
        <x-button type="submit">{{ $submitLabel }}</x-button>
        <x-button :href="route('vendor.shipping-methods.index')" variant="secondary">Cancel</x-button>
    </div>
</form>
