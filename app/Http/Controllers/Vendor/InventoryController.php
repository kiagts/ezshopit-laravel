<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\DecreaseInventory;
use App\Actions\Inventory\IncreaseInventory;
use App\Actions\Inventory\UpdateInventoryThreshold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Inventory\AdjustInventoryRequest;
use App\Http\Requests\Vendor\Inventory\DecreaseInventoryRequest;
use App\Http\Requests\Vendor\Inventory\IncreaseInventoryRequest;
use App\Http\Requests\Vendor\Inventory\UpdateInventoryThresholdRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        $vendor = $request->user()->vendor;
        $search = $validated['q'] ?? null;

        $inventories = Inventory::query()
            ->with(['variant.attributeValues', 'variant.product'])
            ->whereHas('variant.product', fn (Builder $query) => $query->where('vendor_id', $vendor->id))
            ->when(filled($search), fn (Builder $query) => $query->whereHas('variant', function (Builder $query) use ($search): void {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
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
            ->paginate(15)
            ->withQueryString();

        return view('vendor.inventory.index', [
            'inventories' => $inventories,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function product(Product $product): View
    {
        Gate::authorize('manage', $product);

        return view('vendor.inventory.product', [
            'product' => $product->load(['variants.inventory', 'variants.attributeValues']),
        ]);
    }

    public function history(Product $product, ProductVariant $variant): View
    {
        Gate::authorize('manage', $product);
        $this->ensureVariantBelongsToProduct($product, $variant);

        $inventory = $variant->inventory()->firstOrCreate([], [
            'on_hand' => 0,
            'reserved' => 0,
            'low_stock_threshold' => (int) config('marketplace.inventory.default_low_stock_threshold', 5),
        ]);

        return view('vendor.inventory.history', [
            'product' => $product,
            'variant' => $variant->load('attributeValues'),
            'inventory' => $inventory,
            'movements' => $inventory->movements()->with('createdBy')->paginate(20),
        ]);
    }

    public function increase(IncreaseInventoryRequest $request, Product $product, ProductVariant $variant, IncreaseInventory $increaseInventory): RedirectResponse
    {
        return $this->mutate($product, $variant, fn () => $increaseInventory->handle(
            $variant,
            (int) $request->validated('quantity'),
            $request->user(),
            $request->validated('reason') ?: null,
        ), 'Stock increased.');
    }

    public function decrease(DecreaseInventoryRequest $request, Product $product, ProductVariant $variant, DecreaseInventory $decreaseInventory): RedirectResponse
    {
        return $this->mutate($product, $variant, fn () => $decreaseInventory->handle(
            $variant,
            (int) $request->validated('quantity'),
            $request->user(),
            $request->validated('reason') ?: null,
        ), 'Stock decreased.');
    }

    public function adjust(AdjustInventoryRequest $request, Product $product, ProductVariant $variant, AdjustInventory $adjustInventory): RedirectResponse
    {
        return $this->mutate($product, $variant, fn () => $adjustInventory->handle(
            $variant,
            (int) $request->validated('on_hand'),
            $request->user(),
            (string) $request->validated('reason'),
        ), 'Stock adjusted.');
    }

    public function threshold(UpdateInventoryThresholdRequest $request, Product $product, ProductVariant $variant, UpdateInventoryThreshold $updateInventoryThreshold): RedirectResponse
    {
        return $this->mutate($product, $variant, fn () => $updateInventoryThreshold->handle(
            $variant,
            (int) $request->validated('low_stock_threshold'),
        ), 'Low-stock threshold updated.');
    }

    private function mutate(Product $product, ProductVariant $variant, callable $callback, string $message): RedirectResponse
    {
        Gate::authorize('manage', $product);
        $this->ensureVariantBelongsToProduct($product, $variant);

        try {
            $callback();
        } catch (ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->back()->with('status', $message);
    }

    private function ensureVariantBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        abort_unless($variant->product_id === $product->id, 404);
    }
}
