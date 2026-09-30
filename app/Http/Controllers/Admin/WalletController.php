<?php
namespace App\Http\Controllers\Admin;

use App\Enums\{UserRole, WalletStatus};
use App\Http\Controllers\Controller;
use App\Models\{User, Wallet};
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $customers = User::where('role', UserRole::Customer)
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->orderBy('id')->paginate(20)->withQueryString();
        return view('admin.wallets.index', compact('customers'));
    }

    public function show(Request $request, User $customer, WalletService $service)
    {
        abort_unless($customer->isCustomer(), 404);
        $currencies = $service->currencies();
        $request->validate(['currency' => ['nullable', 'string', Rule::in($currencies)]]);
        $currency = $request->input('currency', Money::currencyCode());
        $wallet = Wallet::where('user_id', $customer->id)->where('currency_code', $currency)->first();
        $balance = $wallet ? $service->balance($wallet) : 0;
        $transactions = $wallet?->transactions()->latest('id')->paginate(25)->withQueryString();
        return view('admin.wallets.show', compact('customer', 'currencies', 'currency', 'wallet', 'balance', 'transactions'));
    }

    public function transact(Request $request, User $customer, WalletService $service)
    {
        abort_unless($customer->isCustomer(), 404);
        $data = $request->validate([
            'currency' => ['required', 'string', Rule::in($service->currencies())],
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'string', 'max:16', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $wallet = $service->wallet($customer, $data['currency']);
        $service->{$data['type']}($wallet, Money::parseDecimal($data['amount']), $data['reason'],
            'admin:'.$data['idempotency_key'], $request->user()->id);
        return redirect()->route('admin.wallets.show', ['customer' => $customer, 'currency' => $data['currency']])->with('status', 'Wallet transaction recorded.');
    }

    public function status(Request $request, Wallet $wallet, WalletService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'frozen'])]]);
        $service->setStatus($wallet, WalletStatus::from($data['status']));
        return back()->with('status', 'Wallet status updated.');
    }
}
