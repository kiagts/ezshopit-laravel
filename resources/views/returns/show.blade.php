@extends($layout)
@section('title', $return->return_number)
@section('account_title', 'Return details')
@section('account_active', 'returns')
@section($section)
<div class="space-y-6">
    @if($prefix === 'admin' && $return->status === \App\Enums\ReturnStatus::RefundPending)
        <x-button :href="route('admin.returns.refund.create',$return)">Review refund</x-button>
    @endif
    @if($prefix !== 'vendor')
        @foreach($return->refunds as $refund)<x-refund-breakdown :refund="$refund" />@endforeach
    @else
        @foreach($return->refunds as $refund)<p>{{ $refund->refund_number }} · {{ $refund->status->label() }} · Seller reversal: {{ \App\Support\Money::format((int)$refund->allocations->sum('seller_amount'),$refund->currency) }}</p>@endforeach
    @endif
    <a class="underline" href="{{ route($prefix.'.returns.index') }}">All returns</a>
    <h1 class="break-all text-2xl font-semibold">{{ $return->return_number }}</h1>
    <p>{{ $return->status->label() }} · {{ $return->order->order_number }} · {{ $return->vendorOrder?->vendor?->display_name ?? 'Legacy request' }}</p>
    @if($prefix === 'admin')<p>Customer: {{ $return->user->name }} · Seller order: {{ $return->vendorOrder?->vendor_order_number ?? 'Unassigned' }}</p>@endif
    <section class="rounded-xl border bg-white p-5">
        <h2 class="font-semibold">Reason: {{ str($return->reason)->replace('_', ' ')->title() }}</h2>
        <p class="mt-2 whitespace-pre-wrap break-words">{{ $return->notes }}</p>
        @foreach($return->items as $item)
        <div class="mt-4 border-t pt-3"><p>{{ $item->orderItem->product_name }} · Quantity {{ $item->quantity }}</p>
            @if($item->condition && $item->condition !== \App\Enums\ReturnCondition::Uninspected)
            <p class="text-sm">Inspection: {{ $item->condition->label() }} · {{ $item->inspected_quantity }} inspected · {{ str($item->resolution)->replace('_', ' ')->title() }}</p>
            <p class="break-words text-sm">{{ $item->inspection_note }}</p>
            @if($item->restocked_at)<p class="text-sm">Restocked {{ $item->inspected_quantity }} item(s).</p>@endif
            @endif
        </div>
        @endforeach
    </section>
    <section><h2 class="mb-3 font-semibold">Evidence</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach($return->evidence as $image)<a href="{{ route($prefix.'.returns.evidence', $image) }}"><img class="h-32 w-full rounded object-contain" src="{{ route($prefix.'.returns.evidence', $image) }}" alt="Return evidence {{ $loop->iteration }}"></a>@endforeach</div>
        @can('upload', $return)
        <form class="mt-4 space-y-3" method="POST" enctype="multipart/form-data" action="{{ route('account.returns.upload', $return) }}">@csrf
            <label class="block">Images (JPEG, PNG or WebP; up to 5 MB each)<input class="block w-full" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
            <x-button type="submit">Upload evidence</x-button>
        </form>
        @endcan
    </section>
    @if(count($targets))
    <form class="space-y-3 rounded-xl border p-5" method="POST" action="{{ route($prefix.'.returns.transition', $return) }}">@csrf
        <label class="block">Next status<select class="block w-full rounded border p-2" name="status">@foreach($targets as $target)<option value="{{ $target->value }}">{{ $target->label() }}</option>@endforeach</select></label>
        <label class="block">Public explanation (required for rejection or admin override)<textarea class="block w-full rounded border p-2" name="note" maxlength="2000"></textarea></label>
        <x-button type="submit">Update return</x-button>
    </form>
    @endif
    @if($return->status === \App\Enums\ReturnStatus::Received)
    @can('inspect', $return)
    <form class="space-y-5 rounded-xl border p-5" method="POST" action="{{ route($prefix.'.returns.inspect', $return) }}">@csrf
        <h2 class="font-semibold">Inspect returned items</h2>
        @foreach($return->items as $item)
        <fieldset class="grid gap-3 sm:grid-cols-2"><legend>{{ $item->orderItem->product_name }}</legend>
            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
            <label>Condition<select class="block w-full rounded border p-2" name="items[{{ $loop->index }}][condition]">@foreach($conditions as $condition)<option value="{{ $condition->value }}">{{ $condition->label() }}</option>@endforeach</select></label>
            <label>Inspected quantity<input class="block w-full rounded border p-2" type="number" min="0" max="{{ $item->quantity }}" name="items[{{ $loop->index }}][inspected_quantity]" value="{{ $item->quantity }}" required></label>
            <label>Resolution<select class="block w-full rounded border p-2" name="items[{{ $loop->index }}][resolution]"><option value="refund">Refund review</option><option value="reject">Reject</option><option value="no_action">No action</option></select></label>
            <label>Public inspection note<textarea class="block w-full rounded border p-2" name="items[{{ $loop->index }}][inspection_note]" maxlength="2000"></textarea></label>
        </fieldset>
        @endforeach
        <p class="text-sm">Restockable inspected quantities will be restored to sellable inventory. This action does not issue a refund.</p>
        <x-button type="submit">Record inspection</x-button>
    </form>
    @endcan
    @endif
    @if($return->status === \App\Enums\ReturnStatus::RefundPending)<p>Awaiting financial refund review. No refund has been issued by this return workflow.</p>@endif
    <section><h2 class="font-semibold">Timeline</h2><ol class="mt-3 space-y-3">@foreach($return->history as $entry)<li class="rounded border p-3">{{ str($entry->to_status)->replace('_', ' ')->title() }} · {{ $entry->actor_role ?? 'Legacy' }} · {{ $entry->created_at->format('M j, Y H:i') }}<p class="break-words">{{ $entry->note }}</p></li>@endforeach</ol></section>
    @foreach($disputes as $dispute)<p>Order dispute {{ $dispute->dispute_number }}: {{ $dispute->status->label() }}</p>@endforeach
</div>
@endsection
