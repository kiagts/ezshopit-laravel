@extends('account.layout')
@section('title', 'Wallet')
@section('account_title', 'Wallet')
@section('account_active', 'wallet')
@section('account_content')
<div class="space-y-6">@include('wallet.summary') @include('wallet.history')</div>
@endsection
