<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorDashboard\VendorDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, VendorDashboardService $dashboard): View
    {
        $vendor = $request->user()->vendor;

        return view('vendor.dashboard', array_merge([
            'user' => $request->user(),
            'vendor' => $vendor,
        ], $dashboard->summaryFor($vendor)));
    }
}
