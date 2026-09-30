@extends('layouts.vendor')

@section('title', 'Notifications')

@section('content')
    @include('notifications._list', [
        'indexRoute' => 'vendor.notifications.index',
        'openRoute' => 'vendor.notifications.open',
        'readRoute' => 'vendor.notifications.read',
        'readAllRoute' => 'vendor.notifications.read-all',
    ])
@endsection
