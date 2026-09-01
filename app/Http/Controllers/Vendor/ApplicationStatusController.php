<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationStatusController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $vendor = $request->user()->vendor;

        if ($vendor === null || $vendor->status === VendorStatus::Draft) {
            return redirect()->route('vendor.onboarding.edit');
        }

        if ($vendor->status === VendorStatus::Approved) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.application.status', [
            'vendor' => $vendor,
        ]);
    }
}
