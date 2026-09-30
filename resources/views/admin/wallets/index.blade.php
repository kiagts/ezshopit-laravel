@extends('layouts.admin')
@section('title', 'Customer wallets')
@section('content')
<h1 class="text-2xl font-semibold">Customer wallets</h1>
<form method="GET" class="my-6 flex gap-3"><label class="flex-1">Find customer<input name="q" value="{{ request('q') }}" maxlength="100" class="block w-full rounded border p-3"></label><button class="self-end rounded border p-3">Search</button></form>
<div class="divide-y rounded border bg-white">
@forelse($customers as $customer)
<a href="{{ route('admin.wallets.show', $customer) }}" class="block p-4 hover:bg-neutral-50">{{ $customer->name }} <span class="break-all text-neutral-500">{{ $customer->email }}</span></a>
@empty <p class="p-4">No customers found.</p> @endforelse
</div><div class="mt-6">{{ $customers->links() }}</div>
@endsection
