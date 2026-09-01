<?php

namespace App\Http\Controllers\Account;

use App\Actions\CustomerAddresses\DeleteCustomerAddress;
use App\Actions\CustomerAddresses\SetDefaultCustomerAddress;
use App\Actions\CustomerAddresses\StoreCustomerAddress;
use App\Actions\CustomerAddresses\UpdateCustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreCustomerAddressRequest;
use App\Http\Requests\Account\UpdateCustomerAddressRequest;
use App\Models\CustomerAddress;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses.index', [
            'addresses' => $request->user()->addresses()
                ->orderByDesc('is_default')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(),
            'countries' => Countries::supported(),
            'maxAddresses' => (int) config('marketplace.checkout.max_customer_addresses', 10),
        ]);
    }

    public function store(StoreCustomerAddressRequest $request, StoreCustomerAddress $storeAddress): RedirectResponse
    {
        $storeAddress->handle($request->user(), $request->validated());

        return redirect()->route('account.addresses.index')->with('status', 'Address saved.');
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $this->authorizeOwned($request, $address);

        return view('account.addresses.edit', [
            'address' => $address,
            'countries' => Countries::supported(),
        ]);
    }

    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $address, UpdateCustomerAddress $updateAddress): RedirectResponse
    {
        $this->authorizeOwned($request, $address);

        $updateAddress->handle($address, $request->validated());

        return redirect()->route('account.addresses.index')->with('status', 'Address updated.');
    }

    public function destroy(Request $request, CustomerAddress $address, DeleteCustomerAddress $deleteAddress): RedirectResponse
    {
        $this->authorizeOwned($request, $address);

        $deleteAddress->handle($address);

        return redirect()->route('account.addresses.index')->with('status', 'Address deleted.');
    }

    public function setDefault(Request $request, CustomerAddress $address, SetDefaultCustomerAddress $setDefaultAddress): RedirectResponse
    {
        $this->authorizeOwned($request, $address);

        $setDefaultAddress->handle($address);

        return redirect()->route('account.addresses.index')->with('status', 'Default address updated.');
    }

    private function authorizeOwned(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
