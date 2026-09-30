@extends('layouts.admin')

@section('title', 'Edit Homepage Section')

@section('content')
    <x-card class="p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-neutral-950">{{ $section->name }}</h1>
                <p class="mt-1 text-sm text-neutral-600">{{ $section->type->label() }} section. Type is immutable; create a new section for a different renderer.</p>
            </div>
            <a href="{{ route('admin.homepage.index') }}" class="text-sm font-semibold text-neutral-700 hover:text-neutral-950">Back to homepage</a>
        </div>

        <form method="POST" action="{{ route('admin.homepage.sections.update', $section) }}" enctype="multipart/form-data" class="mt-6">
            @csrf
            @method('PUT')
            @include('admin.homepage.partials.form')
        </form>
    </x-card>
@endsection
