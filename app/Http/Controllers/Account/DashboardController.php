<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Account\CustomerAccountDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CustomerAccountDashboardService $dashboard): View
    {
        return view('account.dashboard', array_merge([
            'user' => $request->user(),
        ], $dashboard->summaryFor($request->user())));
    }
}
