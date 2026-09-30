<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateCustomerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('account.profile');
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->validated())->save();

        return redirect()
            ->route('account.profile.edit')
            ->with('status', 'Profile updated.');
    }
}
