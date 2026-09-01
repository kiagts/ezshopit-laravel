@props([
    'address',
    'selected' => false,
    'selectable' => false,
    'name' => 'address_id',
])

@php
    $lines = app(\App\Support\AddressFormatter::class)->lines($address);
@endphp

@if ($selectable)
    <label class="block cursor-pointer rounded-lg border bg-white p-5 shadow-sm transition {{ $selected ? 'border-brand-700 ring-2 ring-brand-100' : 'border-neutral-200 hover:border-neutral-300' }}">
        <div class="flex gap-4">
            <input type="radio" name="{{ $name }}" value="{{ $address->id }}" class="mt-1 h-4 w-4 border-neutral-300 text-brand-700 focus:ring-brand-600" @checked($selected)>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-neutral-950">{{ $address->label ?: 'Delivery address' }}</p>
                    @if ($address->is_default)
                        <span class="rounded-sm bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800">Default</span>
                    @endif
                    @if ($selected)
                        <span class="rounded-sm bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Selected</span>
                    @endif
                </div>
                <p class="mt-3 text-sm font-medium text-neutral-950">{{ $address->recipientName() }}</p>
                <div class="mt-2 space-y-1 text-sm leading-6 text-neutral-600">
                    @foreach ($lines as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                    <p>{{ $address->phone }}</p>
                </div>
            </div>
        </div>
    </label>
@else
    <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <p class="font-semibold text-neutral-950">{{ $address->label ?: 'Delivery address' }}</p>
            @if ($address->is_default)
                <span class="rounded-sm bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800">Default</span>
            @endif
        </div>
        <p class="mt-3 text-sm font-medium text-neutral-950">{{ $address->recipientName() }}</p>
        <div class="mt-2 space-y-1 text-sm leading-6 text-neutral-600">
            @foreach ($lines as $line)
                <p>{{ $line }}</p>
            @endforeach
            <p>{{ $address->phone }}</p>
        </div>

        @if ($slot->isNotEmpty())
            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-neutral-100 pt-4 text-sm">
                {{ $slot }}
            </div>
        @endif
    </div>
@endif
