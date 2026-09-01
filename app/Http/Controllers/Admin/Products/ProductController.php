<?php

namespace App\Http\Controllers\Admin\Products;

use App\Actions\Products\ApproveProduct;
use App\Actions\Products\RejectProduct;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\RejectProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'approval' => ['nullable', Rule::enum(ProductApprovalStatus::class)],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $search = $validated['q'] ?? null;

        $products = Product::query()
            ->with(['vendor.user', 'category', 'brand', 'primaryImage', 'firstGalleryImage'])
            ->withCount('variants')
            ->withMin(['variants as min_base_price' => fn ($query) => $query->where('is_active', true)], 'base_price')
            ->withMax(['variants as max_base_price' => fn ($query) => $query->where('is_active', true)], 'base_price')
            ->when(filled($search), fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($query) => $query
                        ->where('display_name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%"))
                    ->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', "%{$search}%"));
            }))
            ->when(filled($validated['status'] ?? null), fn ($query) => $query->where('status', $validated['status']))
            ->when(filled($validated['approval'] ?? null), fn ($query) => $query->where('approval_status', $validated['approval']))
            ->when(filled($validated['type'] ?? null), fn ($query) => $query->where('type', $validated['type']))
            ->when(filled($validated['category_id'] ?? null), fn ($query) => $query->where('category_id', $validated['category_id']))
            ->latest('submitted_at')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'approval' => $validated['approval'] ?? '',
                'type' => $validated['type'] ?? '',
                'category_id' => $validated['category_id'] ?? '',
            ],
            'statuses' => ProductStatus::cases(),
            'approvals' => ProductApprovalStatus::cases(),
            'types' => ProductType::cases(),
            'categories' => Category::query()->active()->ordered()->get(['id', 'name']),
            'money' => Money::class,
        ]);
    }

    public function show(Product $product): View
    {
        $product->load([
            'vendor.user',
            'category',
            'brand',
            'galleryImages',
            'variants.attributeValues.productAttribute',
            'variants.inventory',
            'variants.images',
            'specifications.attribute',
            'specifications.values',
        ]);

        return view('admin.products.show', [
            'product' => $product,
            'money' => Money::class,
        ]);
    }

    public function approve(Request $request, Product $product, ApproveProduct $approveProduct): RedirectResponse
    {
        try {
            $approveProduct->handle($product, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('admin.products.show', $product)->withErrors($exception->errors());
        }

        return redirect()->route('admin.products.show', $product)->with('status', 'Product approved.');
    }

    public function reject(RejectProductRequest $request, Product $product, RejectProduct $rejectProduct): RedirectResponse
    {
        try {
            $rejectProduct->handle($product, $request->user(), $request->validated('rejection_reason'));
        } catch (ValidationException $exception) {
            return redirect()->route('admin.products.show', $product)->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.products.show', $product)->with('status', 'Product rejected.');
    }
}
