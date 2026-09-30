@extends('layouts.admin') @section('title','Navigation') @section('content')
<div class="space-y-6"><h1 class="text-2xl font-semibold">Navigation</h1><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($menus as $menu)<a href="{{ route('admin.navigation.edit',$menu) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="font-medium">{{ $menu->name }}</div><div class="mt-1 text-sm text-slate-500">{{ $menu->items_count }} items · {{ $menu->is_active ? 'Active' : 'Disabled' }}</div></a>@endforeach</div></div>
@endsection
