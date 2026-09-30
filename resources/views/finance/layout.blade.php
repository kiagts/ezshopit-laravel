@extends($admin ? 'layouts.admin' : 'layouts.vendor')
@section('title', 'Seller Finance — '.$vendor->display_name)
@section('content')
<div class="mb-6"><p class="text-sm text-neutral-500">Seller finance · {{ $vendor->display_name }}</p><h1 class="mt-2 text-2xl font-semibold">@yield('finance_title')</h1><p class="mt-2 text-sm text-neutral-600">Historical {{ $currency }} amounts. No currency conversion. Dates use {{ config('app.timezone') }}.</p></div>
@if($errors->any())<x-alert type="error" class="mb-4">{{ $errors->first() }}</x-alert>@endif
<nav aria-label="Seller financial operations" class="mb-6 flex flex-wrap gap-3 text-sm">
@foreach(['index'=>'Overview','transactions'=>'Transactions','earnings'=>'Earnings','statements'=>'Statements'] as $section=>$label)<a class="inline-flex min-h-11 items-center rounded-md border px-4 hover:bg-neutral-100 focus:outline-2" href="{{ $url($section) }}">{{ $label }}</a>@endforeach
<a class="inline-flex min-h-11 items-center rounded-md border px-4" href="{{ $admin?route('admin.payouts.index',['vendor_id'=>$vendor->id]):route('vendor.finance.payouts',['currency'=>$currency]) }}">Payouts</a>
@if($admin)<a class="inline-flex min-h-11 items-center rounded-md border px-4" href="{{ $url('reconcile') }}">Read-only reconciliation</a>@endif
</nav>
@yield('finance_content')
@endsection
