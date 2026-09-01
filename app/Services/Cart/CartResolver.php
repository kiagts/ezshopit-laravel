<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartResolver
{
    public const SESSION_KEY = 'cart_token';

    public function current(Request $request, bool $create = false): ?Cart
    {
        $user = $request->user();

        if ($user?->isCustomer()) {
            return $create
                ? Cart::query()->firstOrCreate(['user_id' => $user->id], ['token' => null])
                : Cart::query()->where('user_id', $user->id)->first();
        }

        $token = $request->session()->get(self::SESSION_KEY);

        if (! $token) {
            if (! $create) {
                return null;
            }

            $token = (string) Str::ulid();
            $request->session()->put(self::SESSION_KEY, $token);
        }

        return Cart::query()->firstOrCreate([
            'token' => $token,
            'user_id' => null,
        ]);
    }

    public function token(Request $request): ?string
    {
        return $request->session()->get(self::SESSION_KEY);
    }

    public function forgetGuestToken(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
