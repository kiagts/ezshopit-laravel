@extends('account.layout')
@section('title', 'Request a return')
@section('account_title', 'Request a return')
@section('account_active', 'returns')
@section('account_content')
<h2 class="text-xl font-semibold">{{ $order->order_number }}</h2>
<p class="my-4 text-sm text-neutral-600">Choose an item below. Submit a separate request for another seller. You can also add supporting images.</p>
@foreach($eligible as $row)
@php($item = $row['item'])
<section class="my-4 rounded-xl border bg-white p-5">
    <h3 class="font-semibold">{{ $item->product_name }}</h3>
    <p class="text-sm">{{ $item->vendorOrder->vendor->display_name }}</p>
    @if($row['eligibility']['quantity'] > 0)
    <form method="POST" enctype="multipart/form-data" action="{{ route('account.orders.return.store', $order) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
        @csrf
        <input type="hidden" name="items[0][order_item_id]" value="{{ $item->id }}">
        <label>Quantity <input class="block w-full rounded border p-2" type="number" name="items[0][quantity]" min="1" max="{{ $row['eligibility']['quantity'] }}" value="1" required></label>
        <label>Reason <select class="block w-full rounded border p-2" name="reason" required>@foreach($reasons as $reason)<option value="{{ $reason->value }}">{{ $reason->label() }}</option>@endforeach</select></label>
        <label class="sm:col-span-2">Note (required for Other)<textarea class="block w-full rounded border p-2" name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
        <x-button type="submit">Request return</x-button>
        <label class="sm:col-span-2">Evidence images (optional; JPEG, PNG, WebP; 5 MB each)<input class="block w-full" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
    </form>
    @else <p class="mt-3 text-sm">{{ $row['eligibility']['reason'] }}</p> @endif
</section>
@endforeach
@endsection
