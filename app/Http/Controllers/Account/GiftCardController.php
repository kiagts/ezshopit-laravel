<?php
namespace App\Http\Controllers\Account;
use App\Http\Controllers\Controller; use App\Models\GiftCard; use App\Services\GiftCardService; use Illuminate\Http\Request;
class GiftCardController extends Controller { public function index(Request $r,GiftCardService $s){$cards=GiftCard::where('recipient_user_id',$r->user()->id)->latest()->paginate(20);return view('account.gift-cards',compact('cards'));} public function balance(Request $r,GiftCardService $s){$d=$r->validate(['code'=>'required|string|max:40']);$card=$s->find($d['code']);if(!$card||!$card->usable())return back()->withErrors(['code'=>'Gift card is unavailable or expired.']);return back()->with('gift_card_balance',['currency'=>$card->currency_code,'balance'=>$card->balance(),'last_four'=>$card->code_last_four]);} }
