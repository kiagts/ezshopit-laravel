@php
    $address ??= null;
    $method ??= 'POST';
    $submitLabel ??= 'Save Address';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input label="Address label" name="label" placeholder="Home, Office, Parents" value="{{ old('label', $address?->label) }}" />
        <x-input label="Phone" name="phone" placeholder="+92 300 1234567" value="{{ old('phone', $address?->phone) }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input label="First name" name="first_name" value="{{ old('first_name', $address?->first_name) }}" />
        <x-input label="Last name" name="last_name" value="{{ old('last_name', $address?->last_name) }}" />
    </div>

    <x-select label="Country" name="country_code">
        <option value="">Choose a country</option>
        @foreach ($countries as $code => $name)
            <option value="{{ $code }}" @selected(strtoupper((string) old('country_code', $address?->country_code)) === $code)>{{ $name }}</option>
        @endforeach
    </x-select>

    <x-input label="Address line 1" name="address_line_1" placeholder="Street address, building, area" value="{{ old('address_line_1', $address?->address_line_1) }}" />
    <x-input label="Address line 2" name="address_line_2" placeholder="Apartment, floor, suite" value="{{ old('address_line_2', $address?->address_line_2) }}" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-input label="City" name="city" value="{{ old('city', $address?->city) }}" />
        <x-input label="State / Region" name="state_region" value="{{ old('state_region', $address?->state_region) }}" />
        <x-input label="Postal code" name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}" />
    </div>

    <input type="hidden" name="is_default" value="0">
    <label class="flex items-start gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm">
        <input type="checkbox" name="is_default" value="1" class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600" @checked(old('is_default', $address?->is_default ?? false))>
        <span>
            <span class="block font-medium text-neutral-950">Set as default</span>
            <span class="mt-1 block leading-6 text-neutral-600">This changes your saved default address, but checkout address selection stays separate.</span>
        </span>
    </label>

    <div class="flex flex-wrap items-center gap-3">
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
