<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\ShippingMethods\StoreShippingMethod;
use App\Actions\ShippingMethods\UpdateShippingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\ShippingMethods\StoreShippingMethodRequest;
use App\Http\Requests\Vendor\ShippingMethods\UpdateShippingMethodRequest;
use App\Models\ShippingMethod;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingMethodController extends Controller
{
    public function index(Request $request): View
    {
        return view('vendor.shipping-methods.index', [
            'methods' => $request->user()->vendor->shippingMethods()
                ->with('countries')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('vendor.shipping-methods.create', [
            'method' => new ShippingMethod(['ships_to_all_supported_countries' => true, 'is_active' => true]),
            'countries' => Countries::supported(),
        ]);
    }

    public function store(StoreShippingMethodRequest $request, StoreShippingMethod $storeShippingMethod): RedirectResponse
    {
        $storeShippingMethod->handle($request->user()->vendor, $request->validated());

        return redirect()->route('vendor.shipping-methods.index')->with('status', 'Shipping method created.');
    }

    public function edit(Request $request, ShippingMethod $shippingMethod): View
    {
        $this->authorizeOwned($request, $shippingMethod);

        return view('vendor.shipping-methods.edit', [
            'method' => $shippingMethod->load('countries'),
            'countries' => Countries::supported(),
        ]);
    }

    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod, UpdateShippingMethod $updateShippingMethod): RedirectResponse
    {
        $this->authorizeOwned($request, $shippingMethod);

        $updateShippingMethod->handle($shippingMethod, $request->validated());

        return redirect()->route('vendor.shipping-methods.index')->with('status', 'Shipping method updated.');
    }

    public function destroy(Request $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->authorizeOwned($request, $shippingMethod);

        $shippingMethod->delete();

        return redirect()->route('vendor.shipping-methods.index')->with('status', 'Shipping method deleted.');
    }

    private function authorizeOwned(Request $request, ShippingMethod $shippingMethod): void
    {
        abort_unless($shippingMethod->vendor_id === $request->user()->vendor?->id, 404);
    }
}
