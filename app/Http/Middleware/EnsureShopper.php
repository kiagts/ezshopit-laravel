<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopper
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user() && ! $request->user()->isCustomer(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
