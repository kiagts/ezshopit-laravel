<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingMethodController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'vendor_id' => $request->integer('vendor_id') ?: null,
            'status' => $request->string('status')->toString(),
        ];

        $methods = ShippingMethod::query()
            ->with(['vendor', 'countries'])
            ->when($filters['q'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn (Builder $query): Builder => $query->where('display_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['vendor_id'], fn (Builder $query, int $vendorId): Builder => $query->where('vendor_id', $vendorId))
            ->when($filters['status'] === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->orderBy('vendor_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.shipping-methods.index', [
            'methods' => $methods,
            'vendors' => Vendor::query()->approved()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => $filters,
        ]);
    }
}
