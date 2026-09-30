<?php
namespace App\Http\Controllers\Storefront;
use App\Http\Controllers\Controller; use App\Services\MarketService; use Illuminate\Http\Request;
class MarketController extends Controller { public function store(Request $r,MarketService $s){$r->validate(['market'=>'required|string|max:32']);$m=$s->select($r->string('market')->toString());abort_unless($m,404);$r->session()->put('market_code',$m->code);return redirect()->back()->with('status','Market updated.');} }
