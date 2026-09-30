@extends($prefix==='account'?'account.layout':'layouts.'.$prefix)
@section($prefix==='account'?'account_content':'content')
<h1 class="text-2xl font-semibold">{{ __('notifications.preferences') }}</h1>
<p class="my-4">{{ __('notifications.required') }}</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route($prefix.'.notifications.preferences.update') }}" class="space-y-4">@csrf @method('PUT')
<label class="block">Notification language<select name="locale" class="m-2 rounded border p-2">@foreach(['en'=>'English','ur'=>'اردو','ar'=>'العربية'] as $code=>$label)<option value="{{ $code }}" @selected((auth()->user()->notification_locale??'en')===$code)>{{ $label }}</option>@endforeach</select></label>
@foreach($categories as $category)
<fieldset class="rounded border p-4"><legend>{{ ucfirst(str_replace('_',' ',$category->value)) }} {{ $category->required()?'(Required)':'(Optional)' }}</legend>
@foreach(['database_enabled'=>'In-app','email_enabled'=>'Email'] as $key=>$label)
<input type="hidden" name="preferences[{{ $category->value }}][{{ $key }}]" value="{{ $category->required()?1:0 }}">
<label class="mr-5 inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="preferences[{{ $category->value }}][{{ $key }}]" value="1" @checked($category->required()||($preferences[$category->value]->$key??true)) @disabled($category->required())>{{ $label }}</label>
@endforeach</fieldset>
@endforeach
<x-button type="submit">Save preferences</x-button>
</form>
@endsection
