@extends($layout)
@section('title', 'Returns')
@section('account_title', 'Returns')
@section('account_active', 'returns')
@section($section)
<div class="space-y-6">
    <h1 class="text-2xl font-semibold">Returns</h1>
    <form method="GET" class="flex flex-wrap gap-3">
        <label>Return or order number <input class="block rounded border p-2" name="q" value="{{ request('q') }}" maxlength="100"></label>
        <label>Status <select class="block rounded border p-2" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        <label>Date <input class="block rounded border p-2" type="date" name="date" value="{{ request('date') }}"></label>
        @if($prefix === 'admin')
        <label>Vendor ID <input class="block rounded border p-2" type="number" min="1" name="vendor" value="{{ request('vendor') }}"></label>
        <label>Customer ID <input class="block rounded border p-2" type="number" min="1" name="customer" value="{{ request('customer') }}"></label>
        @endif
        <x-button type="submit">Filter</x-button>
    </form>
    @forelse($returns as $return)
    <a class="block rounded-xl border bg-white p-5 focus-visible:ring-2" href="{{ route($prefix.'.returns.show', $return) }}">
        <span class="font-semibold break-all">{{ $return->return_number }}</span> · {{ $return->status->label() }}
        <p class="mt-2 text-sm">{{ $return->order->order_number }} · {{ $return->vendorOrder?->vendor?->display_name ?? 'Legacy request' }} · {{ $return->items_count }} item(s) · {{ $return->created_at->format('M j, Y') }}</p>
    </a>
    @empty <p>No returns found.</p> @endforelse
    {{ $returns->links() }}
</div>
@endsection

