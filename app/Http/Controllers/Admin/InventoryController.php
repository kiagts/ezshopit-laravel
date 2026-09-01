<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        $search = $validated['q'] ?? null;

        $inventories = Inventory::query()
            ->with(['variant.attributeValues', 'variant.product.vendor'])
            ->when(filled($search), fn (Builder $query) => $query->whereHas('variant', function (Builder $query) use ($search): void {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhereHas('vendor', fn (Builder $query) => $query
                                ->where('display_name', 'like', "%{$search}%")
                                ->orWhere('business_name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%"));
                    });
            }))
            ->when(($validated['status'] ?? null) === 'in_stock', fn (Builder $query) => $query->inStock())
            ->when(($validated['status'] ?? null) === 'low_stock', fn (Builder $query) => $query->lowStock())
            ->when(($validated['status'] ?? null) === 'out_of_stock', fn (Builder $query) => $query->outOfStock())
            ->join('product_variants', 'inventories.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereNull('product_variants.deleted_at')
            ->whereNull('products.deleted_at')
            ->orderBy('products.name')
            ->orderBy('product_variants.sort_order')
            ->select('inventories.*')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inventory.index', [
            'inventories' => $inventories,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }
}
