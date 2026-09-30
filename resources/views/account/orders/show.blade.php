@extends('account.layout')

@section('title', $order->order_number)
@section('account_active', 'orders')
@section('account_badge')
    {{ $order->status->label() }}
@endsection
@section('account_title', $order->order_number)
@section('account_description')
    Placed {{ $order->placed_at?->format('M j, Y g:i A') }}
@endsection

@section('account_actions')
    <x-button :href="route('account.documents.invoice', $order)" variant="secondary">{{ __('documents.download') }}</x-button>
    <x-button :href="route('account.documents.print', $order)" variant="secondary">{{ __('documents.print') }}</x-button>
    <x-button :href="route('account.orders.index')" variant="secondary">All Orders</x-button>
@endsection

@section('account_content')
    <div class="mb-4"><x-button :href="route('account.orders.dispute.create', $order)" variant="secondary">Open a dispute</x-button></div>
    @if($hasReturnableItems)
    <div class="mb-5"><x-button :href="route('account.orders.returns.create', $order)" variant="secondary">Return an item</x-button></div>
    @endif
    @include('account.orders._details', ['order' => $order])
@endsection
