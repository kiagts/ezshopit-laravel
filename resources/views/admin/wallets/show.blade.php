@extends('layouts.admin')
@section('title', 'Customer wallet')
@section('content')
<div class="space-y-6">
<h1 class="text-2xl font-semibold">{{ $customer->name }} — Wallet</h1>
@foreach($errors->all() as $error)<p class="text-red-700">{{ $error }}</p>@endforeach
@include('wallet.summary')
<form method="POST" action="{{ route('admin.wallets.transact', $customer) }}" class="grid gap-4 rounded border bg-white p-5 sm:grid-cols-2">
@csrf
<input type="hidden" name="currency" value="{{ $currency }}">
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
<label>Operation<select name="type" class="block w-full rounded border p-3"><option value="credit">Credit</option><option value="debit">Debit</option></select></label>
<label>Amount ({{ $currency }})<input name="amount" value="{{ old('amount') }}" required inputmode="decimal" class="block w-full rounded border p-3"></label>
<label class="sm:col-span-2">Reason<textarea name="reason" required maxlength="500" class="block w-full rounded border p-3">{{ old('reason') }}</textarea></label>
<button class="min-h-11 rounded bg-neutral-950 px-4 text-white">Record transaction</button>
</form>
@if($wallet && $wallet->status !== App\Enums\WalletStatus::Closed)
<form method="POST" action="{{ route('admin.wallets.status', $wallet) }}">@csrf @method('PUT')
<input type="hidden" name="status" value="{{ $wallet->status === App\Enums\WalletStatus::Frozen ? 'active' : 'frozen' }}">
<button class="min-h-11 rounded border px-4">{{ $wallet->status === App\Enums\WalletStatus::Frozen ? 'Unfreeze wallet' : 'Freeze wallet' }}</button></form>
@endif
@include('wallet.history')
</div>
@endsection
