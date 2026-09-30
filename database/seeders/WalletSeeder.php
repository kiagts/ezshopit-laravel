<?php
namespace Database\Seeders;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'customer@example.com')->first();
        if (!$customer || !$customer->isCustomer()) return;
        $service = app(WalletService::class);
        foreach (['USD' => 25000, 'PKR' => 1000000] as $currency => $amount) {
            if (!in_array($currency, $service->currencies(), true)) continue;
            $wallet = $service->wallet($customer, $currency);
            $service->credit($wallet, $amount, 'Demo store credit', "demo:wallet:{$wallet->id}:initial");
        }
    }
}
