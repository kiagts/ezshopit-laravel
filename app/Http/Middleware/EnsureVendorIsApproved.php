<?php

namespace App\Http\Middleware;

use App\Enums\VendorStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user()?->vendor;

        if ($vendor === null || $vendor->status === VendorStatus::Draft) {
            return redirect()->route('vendor.onboarding.edit');
        }

        if (! $vendor->isApproved()) {
            return redirect()->route('vendor.application-status');
        }

        return $next($request);
    }
}
