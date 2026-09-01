<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $expectedRole = UserRole::tryFrom($role);

        abort_if($expectedRole === null, Response::HTTP_FORBIDDEN);
        abort_unless($request->user()?->role === $expectedRole, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
