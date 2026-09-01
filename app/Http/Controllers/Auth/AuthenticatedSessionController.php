<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Actions\Cart\MergeGuestCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        if (filled($request->query('redirect')) && str_starts_with((string) $request->query('redirect'), url('/'))) {
            $request->session()->put('url.intended', $request->query('redirect'));
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request, MergeGuestCart $mergeGuestCart): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $mergeGuestCart->handle($request, $request->user());

        return redirect()->intended($request->user()->dashboardPath());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
