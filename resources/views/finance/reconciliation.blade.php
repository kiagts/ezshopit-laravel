@extends('finance.layout')
@section('finance_title','Read-only reconciliation')
@section('finance_content')
@include('finance.filters')
<x-card><h2 class="text-lg font-semibold">{{ strtoupper($result['status']) }}</h2><p class="mt-3">Ledger balance: {{ $money($result['summary']['ledger_balance']) }} · Reserved: {{ $money($result['summary']['reserved']) }} · Available: {{ $money($result['summary']['available_payout']) }}</p><p class="mt-2 text-sm text-neutral-600">This check does not post, edit or repair financial records.</p></x-card>
<div class="my-5 grid gap-3 sm:grid-cols-3">@foreach($result['checks'] as $check=>$passed)<div class="rounded border bg-white p-3 text-sm">{{ str_replace('_',' ',$check) }}: <strong>{{ $passed?'PASS':'REVIEW' }}</strong></div>@endforeach</div>
@forelse($result['findings'] as $finding)<x-card class="mb-3"><strong>{{ strtoupper($finding['severity']) }} · {{ $finding['check'] }}</strong><p class="mt-2 break-words">{{ $finding['reference'] }}</p><p class="mt-2">{{ $finding['message'] }}</p></x-card>@empty<p>No inconsistencies detected in the checked sources.</p>@endforelse
@endsection
