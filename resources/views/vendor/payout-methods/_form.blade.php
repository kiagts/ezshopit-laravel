<div class="grid gap-5 lg:grid-cols-2">
    <x-input label="Label" name="label" value="{{ old('label', $method->label ?? 'Primary bank account') }}" placeholder="Primary bank account" required />
    <x-input label="Account holder" name="account_holder_name" value="{{ old('account_holder_name', $method->account_holder_name ?? '') }}" placeholder="Business or account holder name" required />
    <x-input label="Bank name" name="bank_name" value="{{ old('bank_name', $method->bank_name ?? '') }}" placeholder="Demo Bank" required />

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-neutral-800">Country</span>
        <select name="country_code" class="block h-11 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm transition focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">Select country</option>
            @foreach ($countries as $code => $name)
                <option value="{{ $code }}" @selected(old('country_code', $method->country_code ?? '') === $code)>{{ $name }}</option>
            @endforeach
        </select>
        @error('country_code')
            <span class="mt-2 block text-sm text-red-700">{{ $message }}</span>
        @enderror
    </label>

    <div>
        <x-input label="{{ isset($method) ? 'New account number' : 'Account number' }}" name="account_number" value="{{ old('account_number') }}" placeholder="{{ isset($method) ? 'Leave blank to keep '.$method->maskedAccountNumber() : 'Account number' }}" />
        @isset($method)
            <p class="mt-2 text-xs text-neutral-500">Current account: {{ $method->maskedAccountNumber() ?? 'Not provided' }}</p>
        @endisset
    </div>

    <div>
        <x-input label="{{ isset($method) ? 'New IBAN' : 'IBAN' }}" name="iban" value="{{ old('iban') }}" placeholder="{{ isset($method) ? 'Leave blank to keep '.$method->maskedIban() : 'IBAN, if used' }}" />
        @isset($method)
            <p class="mt-2 text-xs text-neutral-500">Current IBAN: {{ $method->maskedIban() ?? 'Not provided' }}</p>
        @endisset
    </div>

    <x-input label="SWIFT/BIC" name="swift_code" value="{{ old('swift_code', $method->swift_code ?? '') }}" placeholder="DEMOUS33" />

    <div>
        <x-input label="{{ isset($method) ? 'New routing number' : 'Routing number' }}" name="routing_number" value="{{ old('routing_number') }}" placeholder="{{ isset($method) ? 'Leave blank to keep '.$method->maskedRoutingNumber() : 'Routing number, if used' }}" />
        @isset($method)
            <p class="mt-2 text-xs text-neutral-500">Current routing: {{ $method->maskedRoutingNumber() ?? 'Not provided' }}</p>
        @endisset
    </div>
</div>

<label class="mt-5 flex items-center gap-3 text-sm text-neutral-700">
    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $method->is_default ?? true)) class="rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
    Use as default payout method
</label>
