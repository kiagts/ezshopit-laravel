<?php
namespace App\Http\Middleware;
use App\Services\LanguageContext;use Closure;use Illuminate\Http\Request;
class SetRequestLanguage {public function handle(Request $r,Closure $next){$l=app(LanguageContext::class)->current($r);app()->setLocale($l->code);$r->attributes->set('language',$l);return $next($r);}}
