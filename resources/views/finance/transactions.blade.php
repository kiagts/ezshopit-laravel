@extends('finance.layout')
@section('finance_title',$isStatement?'Financial statement':'Ledger transactions')
@section('finance_content')
@include('finance.filters',['dateFilters'=>true])
<div class="mb-5 grid gap-4 sm:grid-cols-3">@foreach(['opening'=>'Opening balance','period_activity'=>'All period activity','closing'=>'Closing balance'] as $key=>$label)<x-card><p class="text-sm text-neutral-600">{{ $label }}</p><p class="mt-2 text-lg font-semibold">{{ $money($statement[$key]) }}</p></x-card>@endforeach</div>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-neutral-600">Filtered activity: {{ $money($statement['filtered_activity']) }}. Opening/closing balances include all entry types. Running balances include entries hidden by filters.</p><x-button :href="$url('export')" variant="secondary">Export CSV</x-button></div>
<div class="overflow-x-auto rounded-lg border bg-white"><table class="w-full text-left text-sm"><thead class="bg-neutral-50"><tr>@foreach(['Date','Reference / source','Type / description','Amount','Running balance'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
@forelse($entries as $entry)<tr class="border-t"><td class="whitespace-nowrap p-3">{{ $entry->created_at->format('Y-m-d H:i:s') }}</td><td class="p-3 break-words">
@if($entry->vendorOrder && (int)$entry->vendorOrder->vendor_id===(int)$vendor->id)<a class="underline" href="{{ route(($admin?'admin':'vendor').'.finance.order',$entry->vendorOrder) }}">{{ $entry->vendorOrder->vendor_order_number }}</a>
@elseif($entry->vendorPayout && (int)$entry->vendorPayout->vendor_id===(int)$vendor->id)<a class="underline" href="{{ route(($admin?'admin':'vendor').'.payouts.show',$entry->vendorPayout) }}">{{ $entry->vendorPayout->payout_number }}</a>@else<span>{{ $entry->source_key }}</span>@endif
</td><td class="p-3">{{ $entry->type->label() }}</td><td class="whitespace-nowrap p-3 tabular-nums">{{ $money($entry->amount) }}</td><td class="whitespace-nowrap p-3 tabular-nums">{{ $money($entry->running_balance) }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-neutral-600">No transactions match these filters.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $entries->links() }}</div>
@endsection
