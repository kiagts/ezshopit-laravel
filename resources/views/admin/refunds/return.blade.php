@extends('layouts.admin')
@section('title', 'Refund review')
@section('content')
<h1 class="text-2xl font-semibold">Refund {{ $return->return_number }}</h1>
<p class="my-4">Amounts use original order prices, discounts and tax. Shipping is excluded. External payment refunds will await a provider.</p>
<form method="POST" action="{{ route('admin.returns.refund.store',$return) }}" class="space-y-4">
@csrf
<input type="hidden" name="key" value="{{ old('key', 'return:'.\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="reason" value="Inspected return {{ $return->return_number }}">
@foreach($rows as $row)
<div class="rounded border p-4">
    <p>{{ $row['item']->orderItem->product_name }}</p>
    <p>Available refund: {{ \App\Support\Money::format($row['quote']['amount'],$return->order->currency) }} (including allocated tax {{ \App\Support\Money::format($row['quote']['tax_amount'],$return->order->currency) }})</p>
    <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $row['item']->order_item_id }}">
    <label>Quantity<input class="block rounded border p-2" type="number" name="items[{{ $loop->index }}][quantity]" min="1" max="{{ $row['quantity'] }}" value="{{ $row['quantity'] }}" required></label>
</div>
@endforeach
@if(count($rows))<x-button type="submit">Process refund</x-button>@else<p>All refundable quantities are already reserved or refunded.</p>@endif
</form>
@endsection

