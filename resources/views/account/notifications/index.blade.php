@extends('account.layout')

@section('title', 'Notifications')
@section('account_active', 'notifications')
@section('account_badge', 'Notifications')
@section('account_title', 'Notifications')
@section('account_description', 'Read account, order, payment, refund, and marketplace updates.')

@section('account_content')
    @include('notifications._list', [
        'indexRoute' => 'account.notifications.index',
        'openRoute' => 'account.notifications.open',
        'readRoute' => 'account.notifications.read',
        'readAllRoute' => 'account.notifications.read-all',
    ])
@endsection
