@extends('layouts.admin')

@section('title', 'Create Homepage Section')

@section('content')
    <x-card class="p-6">
        <h1 class="text-xl font-semibold text-neutral-950">Create homepage section</h1>
        <p class="mt-1 text-sm text-neutral-600">Choose a controlled section type and configure only the fields supported by that renderer.</p>

        <form method="POST" action="{{ route('admin.homepage.sections.store') }}" enctype="multipart/form-data" class="mt-6">
            @csrf
            @include('admin.homepage.partials.form')
        </form>
    </x-card>
@endsection
