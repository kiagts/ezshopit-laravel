@extends('finance.layout')
@section('finance_title','Financial overview')
@section('finance_content')
@include('finance.filters')
@if($summary['ledger_balance']<0)<x-alert type="warning" class="mb-6">Your seller account has a negative balance from posted deductions. Future earnings may offset it. Payout availability is zero until sufficient funds are available.</x-alert>@endif
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach(['gross_sales'=>'Gross sales including seller shipping','vendor_discounts'=>'Vendor-funded discounts','commission'=>'Commission charged','refunds'=>'Refund seller impact','commission_reversed'=>'Commission reversed','net_earnings'=>'Net seller earnings before payouts','paid_payouts'=>'Paid payouts','ledger_balance'=>'Current ledger balance','held_balance'=>'Funds under earnings hold','available_ledger'=>'Available ledger funds','reserved'=>'Reserved payouts','available_payout'=>'Available for payout'] as $key=>$label)
<x-card><p class="text-sm text-neutral-600">{{ $label }}</p><p class="mt-3 text-xl font-semibold tabular-nums">{{ $money($summary[$key]) }}</p></x-card>
@endforeach
</div><p class="mt-5 text-sm text-neutral-600">All-time posted amounts. Marketplace-funded discounts do not reduce seller earnings. Customer tax is not seller revenue in the current ledger. Availability respects the existing earnings hold and active payout reservations.</p>
@endsection
