<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorPayoutMethodType;
use App\Http\Controllers\Controller;
use App\Models\VendorPayoutMethod;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayoutMethodController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor;

        return view('vendor.payout-methods.index', [
            'vendor' => $vendor,
            'methods' => $vendor->payoutMethods()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('vendor.payout-methods.create', [
            'countries' => Countries::supported(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        $validated = $this->validatedMethod($request, true);

        DB::transaction(function () use ($vendor, $validated): void {
            $isDefault = (bool) ($validated['is_default'] ?? false) || ! $vendor->payoutMethods()->exists();

            $method = $vendor->payoutMethods()->create([
                ...$validated,
                'type' => VendorPayoutMethodType::BankTransfer,
                'is_default' => $isDefault,
            ]);

            if ($isDefault) {
                $this->makeDefault($method);
            }
        });

        return redirect()
            ->route('vendor.payout-methods.index')
            ->with('status', 'Payout method saved.');
    }

    public function edit(Request $request, VendorPayoutMethod $payoutMethod): View
    {
        abort_unless((int) $payoutMethod->vendor_id === (int) $request->user()->vendor->id, 404);

        return view('vendor.payout-methods.edit', [
            'method' => $payoutMethod,
            'countries' => Countries::supported(),
        ]);
    }

    public function update(Request $request, VendorPayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless((int) $payoutMethod->vendor_id === (int) $request->user()->vendor->id, 404);

        $validated = $this->validatedMethod($request, false, $payoutMethod);

        foreach (['account_number', 'iban', 'routing_number'] as $field) {
            if (($validated[$field] ?? null) === null) {
                unset($validated[$field]);
            }
        }

        $isDefault = (bool) ($validated['is_default'] ?? false);
        DB::transaction(function () use ($payoutMethod, $validated, $isDefault): void {
            $payoutMethod->fill([
                ...$validated,
                'type' => VendorPayoutMethodType::BankTransfer,
                'is_default' => $isDefault ?: $payoutMethod->is_default,
            ])->save();

            if ($isDefault) {
                $this->makeDefault($payoutMethod);
            }
        });

        return redirect()
            ->route('vendor.payout-methods.index')
            ->with('status', 'Payout method updated.');
    }

    public function destroy(Request $request, VendorPayoutMethod $payoutMethod): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless((int) $payoutMethod->vendor_id === (int) $vendor->id, 404);

        DB::transaction(function () use ($vendor, $payoutMethod): void {
            $wasDefault = $payoutMethod->is_default;
            $payoutMethod->delete();

            if ($wasDefault) {
                $vendor->payoutMethods()->latest()->first()?->forceFill(['is_default' => true])->save();
            }
        });

        return redirect()
            ->route('vendor.payout-methods.index')
            ->with('status', 'Payout method removed.');
    }

    public function default(Request $request, VendorPayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless((int) $payoutMethod->vendor_id === (int) $request->user()->vendor->id, 404);

        DB::transaction(fn () => $this->makeDefault($payoutMethod));

        return redirect()
            ->route('vendor.payout-methods.index')
            ->with('status', 'Default payout method updated.');
    }

    private function validatedMethod(Request $request, bool $creating, ?VendorPayoutMethod $method = null): array
    {
        $rules = [
            'label' => ['required', 'string', 'max:80'],
            'account_holder_name' => ['required', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_number' => [$creating ? 'nullable' : 'nullable', 'string', 'max:80'],
            'iban' => ['nullable', 'string', 'max:80'],
            'swift_code' => ['nullable', 'string', 'max:40'],
            'routing_number' => ['nullable', 'string', 'max:80'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'is_default' => ['nullable', 'boolean'],
        ];

        $validated = $request->validate($rules);
        $hasDestination = filled($validated['account_number'] ?? null)
            || filled($validated['iban'] ?? null)
            || (! $creating && (filled($method?->account_number) || filled($method?->iban)));

        if (! $hasDestination) {
            validator([], [])->after(function ($validator): void {
                $validator->errors()->add('account_number', 'Enter an account number or IBAN.');
            })->validate();
        }

        foreach (['account_number', 'iban', 'routing_number'] as $field) {
            if (array_key_exists($field, $validated) && blank($validated[$field])) {
                $validated[$field] = null;
            }
        }

        $validated['country_code'] = filled($validated['country_code'] ?? null) ? strtoupper($validated['country_code']) : null;

        return $validated;
    }

    private function makeDefault(VendorPayoutMethod $method): void
    {
        $method->vendor->payoutMethods()
            ->whereKeyNot($method->id)
            ->update(['is_default' => false]);

        $method->forceFill(['is_default' => true])->save();
    }
}
