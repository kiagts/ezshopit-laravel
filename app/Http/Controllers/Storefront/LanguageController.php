<?php
namespace App\Http\Controllers\Storefront;
use App\Http\Controllers\Controller;use App\Services\LanguageContext;use Illuminate\Http\Request;
class LanguageController extends Controller{public function store(Request $r,LanguageContext $c){$r->validate(['language'=>'required|string|max:12']);abort_unless($c->select($r,$r->string('language')->toString()),404);return back();}}
