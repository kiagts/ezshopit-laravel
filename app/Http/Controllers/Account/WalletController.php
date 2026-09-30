<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\{CurrencyContext, WalletService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __invoke(Request $request, WalletService $service, CurrencyContext $context)
    {
        $currencies = $service->currencies();
        $request->validate(['currency' => ['nullable', 'string', Rule::in($currencies)]]);
        $currency = $request->input('currency', $context->current($request)->code);
        $wallet = Wallet::where('user_id', $request->user()->id)->where('currency_code', $currency)->first();
        $balance = $wallet ? $service->balance($wallet) : 0;
        $transactions = $wallet?->transactions()->latest('id')->paginate(25)->withQueryString();
        return view('account.wallet', compact('currencies', 'currency', 'wallet', 'balance', 'transactions'));
    }
}
