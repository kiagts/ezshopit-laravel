@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
    @include('notifications._list', [
        'indexRoute' => 'admin.notifications.index',
        'openRoute' => 'admin.notifications.open',
        'readRoute' => 'admin.notifications.read',
        'readAllRoute' => 'admin.notifications.read-all',
    ])
@endsection
