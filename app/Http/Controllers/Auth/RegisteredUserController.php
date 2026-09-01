<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Actions\Cart\MergeGuestCart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, MergeGuestCart $mergeGuestCart): RedirectResponse
    {
        $user = new User;
        $user->name = $request->string('name')->toString();
        $user->email = $request->string('email')->lower()->toString();
        $user->password = $request->string('password')->toString();
        $user->role = UserRole::Customer;
        $user->save();

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $mergeGuestCart->handle($request, $user);

        return redirect()->route('account.dashboard');
    }
}
