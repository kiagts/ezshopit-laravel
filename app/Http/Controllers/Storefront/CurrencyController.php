<?php
namespace App\Http\Controllers\Storefront;
use App\Http\Controllers\Controller; use App\Services\CurrencyContext; use Illuminate\Http\Request;
class CurrencyController extends Controller {public function store(Request $r,CurrencyContext $c){$r->validate(['currency'=>'required|string|size:3']);abort_unless($c->select($r,strtoupper($r->string('currency')->toString())),404);return back()->with('status','Currency updated.');}}
