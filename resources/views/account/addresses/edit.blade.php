@extends('account.layout')

@section('title', 'Edit Address')
@section('account_active', 'addresses')
@section('account_badge', 'Address Book')
@section('account_title', 'Edit Address')
@section('account_description', 'Update this private delivery address.')

@section('account_content')
    <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
        @include('account.addresses._form', [
            'address' => $address,
            'action' => route('account.addresses.update', $address),
            'method' => 'PUT',
            'countries' => $countries,
            'submitLabel' => 'Update Address',
        ])
        <x-button :href="route('account.addresses.index')" variant="secondary" class="mt-4">Cancel</x-button>
    </div>
@endsection
