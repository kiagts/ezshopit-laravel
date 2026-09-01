<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Brands\CreateBrand;
use App\Actions\Brands\DeleteBrand;
use App\Actions\Brands\UpdateBrand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Brands\StoreBrandRequest;
use App\Http\Requests\Admin\Brands\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $brands = Brand::query()
            ->search($validated['q'] ?? null)
            ->when(($validated['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.brands.index', [
            'brands' => $brands,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.brands.create', [
            'brand' => new Brand(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(StoreBrandRequest $request, CreateBrand $createBrand): RedirectResponse
    {
        $brand = $createBrand->handle($request);

        return redirect()->route('admin.brands.edit', $brand)->with('status', 'Brand created.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', [
            'brand' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand, UpdateBrand $updateBrand): RedirectResponse
    {
        $updateBrand->handle($brand, $request);

        return redirect()->route('admin.brands.edit', $brand)->with('status', 'Brand updated.');
    }

    public function destroy(Brand $brand, DeleteBrand $deleteBrand): RedirectResponse
    {
        $deleteBrand->handle($brand);

        return redirect()->route('admin.brands.index')->with('status', 'Brand deleted.');
    }
}
