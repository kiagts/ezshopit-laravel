@extends('finance.layout')
@section('finance_title','Seller earnings by order')
@section('finance_content')
@include('finance.filters',['dateFilters'=>true])
<p class="mb-4 text-sm text-neutral-600">Historical order values explain the ledger. Net earnings below are the posted ledger sum for each seller order, before pooled payouts. Unrecognized orders have no posted earnings.</p>
<div class="overflow-x-auto rounded-lg border bg-white"><table class="w-full text-left text-sm"><thead class="bg-neutral-50"><tr>@foreach(['Seller order','Order date','Gross merchandise','Vendor discount','Seller shipping','Commission','Refund impact','Commission reversal','Net posted earnings','Financial status'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
@forelse($orders as $order)<tr class="border-t"><td class="p-3"><a class="underline" href="{{ route(($admin?'admin':'vendor').'.finance.order',$order) }}">{{ $order->vendor_order_number }}</a></td><td class="p-3">{{ $order->order->placed_at?->format('Y-m-d') }}</td>@foreach([$order->items_subtotal,$order->vendor_discount_total,$order->shipping_total,-(int)$order->commission_total,-(int)$order->refund_total,(int)$order->commission_refund_total,(int)$order->ledger_net] as $amount)<td class="whitespace-nowrap p-3 tabular-nums">{{ $money($amount) }}</td>@endforeach<td class="p-3">{{ !$order->commission?'Not recognized':((int)$order->refund_total<0?'Refund impact posted':'Earnings recognized') }}</td></tr>@empty<tr><td colspan="10" class="p-8 text-center">No seller orders in this period.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $orders->links() }}</div>
@endsection
