<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorRegistrationRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function landing(): View
    {
        return view('sell.landing');
    }

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('vendor.apply')
                ->with('status', 'Please use a separate seller account for vendor registration.');
        }

        return view('sell.register');
    }

    public function store(VendorRegistrationRequest $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('vendor.apply')
                ->with('status', 'Please use a separate seller account for vendor registration.');
        }

        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = new User;
            $user->name = $validated['name'];
            $user->email = Str::lower($validated['email']);
            $user->password = $validated['password'];
            $user->role = UserRole::Vendor;
            $user->save();

            $businessName = $validated['business_name'] ?: $validated['name'].' Store';

            $vendor = new Vendor;
            $vendor->user()->associate($user);
            $vendor->status = VendorStatus::Draft;
            $vendor->business_name = $businessName;
            $vendor->display_name = $businessName;
            $vendor->slug = $this->uniqueSlug($businessName);
            $vendor->email = $user->email;
            $vendor->phone = $validated['phone'];
            $vendor->save();

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('vendor.onboarding.edit');
    }

    private function uniqueSlug(string $value): string
    {
        $slug = Str::slug($value) ?: 'store';
        $candidate = $slug;
        $counter = 2;

        while (Vendor::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
