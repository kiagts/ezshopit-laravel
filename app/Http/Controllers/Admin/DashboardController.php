<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'user' => $request->user(),
            'stats' => [
                'vendors' => Vendor::query()->count(),
                'customers' => User::query()->where('role', \App\Enums\UserRole::Customer)->count(),
                'products' => Product::query()->count(),
                'pendingProducts' => Product::query()->where('approval_status', ProductApprovalStatus::Pending)->count(),
                'lowStock' => Inventory::query()->lowStock()->count(),
                'outOfStock' => Inventory::query()->outOfStock()->count(),
            ],
        ]);
    }
}
