<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(),['ar','ur'],true)?'rtl':'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $subject }}</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:Arial,sans-serif;line-height:1.7">
<table role="presentation" width="100%"><tr><td style="padding:24px 12px"><table role="presentation" width="100%" style="max-width:600px;margin:auto;background:white;border:1px solid #e5e5e5;border-radius:12px"><tr><td style="padding:28px">
<a href="{{ url('/') }}" style="font-size:20px;color:#202020;text-decoration:none;font-weight:bold">{{ $brand }}</a>
<h1 style="font-size:23px">{{ $subject }}</h1><p>{{ $greeting }}</p>
@foreach($introLines as $line)<p>{{ $line }}</p>@endforeach
@if($actionUrl)<p style="margin:28px 0"><a href="{{ $actionUrl }}" style="display:inline-block;background:#202020;color:white;padding:12px 22px;border-radius:6px;text-decoration:none">{{ $actionText }}</a></p>@endif
@foreach($outroLines as $line)<p>{{ $line }}</p>@endforeach
@if($supportEmail)<p style="font-size:13px"><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>@endif
</td></tr></table></td></tr></table>
</body></html>
