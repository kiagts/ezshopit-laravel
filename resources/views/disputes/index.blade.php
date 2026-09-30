@extends($layout)
@section('title','Disputes')
@section($section)
<h1 class="text-2xl font-semibold">Disputes</h1>
<p class="mt-2 text-sm text-neutral-600">Review marketplace decisions and conversations. Open a dispute from the relevant order.</p>
<form method="GET" class="my-5 flex flex-wrap gap-3">
    <label class="text-sm">Search<input class="block rounded border p-2" name="q" value="{{ request('q') }}" placeholder="Dispute or order number"></label>
    <label class="text-sm">Status<select class="block rounded border p-2" name="status"><option value="">All</option>@foreach(\App\Enums\DisputeStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>@endforeach</select></label>
    <label class="text-sm">Reason<select class="block rounded border p-2" name="reason"><option value="">All</option>@foreach(\App\Enums\DisputeReason::cases() as $reason)<option value="{{ $reason->value }}" @selected(request('reason')===$reason->value)>{{ $reason->label() }}</option>@endforeach</select></label>
    <label class="text-sm">Opened date<input class="block rounded border p-2" type="date" name="date" value="{{ request('date') }}"></label>
    @if($prefix==='admin')
        @foreach(['vendor'=>'Vendor ID','customer'=>'Customer ID','assigned_admin'=>'Assigned admin ID'] as $key=>$label)<label class="text-sm">{{ $label }}<input class="block w-32 rounded border p-2" type="number" min="1" name="{{ $key }}" value="{{ request($key) }}"></label>@endforeach
    @endif
    <x-button type="submit">Filter</x-button>
</form>
<div class="divide-y rounded-lg border bg-white">
@forelse($disputes as $dispute)
    <article class="flex flex-wrap justify-between gap-4 p-4">
        <div><a class="font-semibold underline" href="{{ route($prefix.'.disputes.show',$dispute) }}">{{ $dispute->dispute_number }}</a><p class="text-sm">{{ $dispute->order->order_number }} · {{ $dispute->vendorOrder?->vendor?->display_name ?? 'Marketplace review' }}</p><p class="text-sm">{{ str_replace('_',' ',$dispute->reason) }}</p></div>
        <div class="text-sm"><p>{{ $dispute->status->label() }}</p><p>Opened {{ $dispute->created_at->format('M j, Y') }}</p><p>Activity {{ ($dispute->last_activity_at ?? $dispute->updated_at)->format('M j, Y H:i') }}</p></div>
    </article>
@empty<p class="p-5">No disputes match these filters.</p>@endforelse
</div>
<div class="mt-5">{{ $disputes->links() }}</div>
@endsection
