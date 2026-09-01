<?php

namespace App\Http\Controllers\Vendor\Products;

use App\Actions\Products\ActivateProduct;
use App\Actions\Products\ArchiveProduct;
use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeactivateProduct;
use App\Actions\Products\SubmitProductForApproval;
use App\Actions\Products\UpdateProduct;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Products\StoreProductRequest;
use App\Http\Requests\Vendor\Products\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $vendor = $request->user()->vendor;
        $search = $validated['q'] ?? null;

        $products = Product::query()
            ->forVendor($vendor)
            ->with(['category', 'brand', 'primaryImage', 'firstGalleryImage'])
            ->withCount('variants')
            ->withMin(['variants as min_base_price' => fn ($query) => $query->where('is_active', true)], 'base_price')
            ->withMax(['variants as max_base_price' => fn ($query) => $query->where('is_active', true)], 'base_price')
            ->when(filled($search), fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', "%{$search}%"));
            }))
            ->when(filled($validated['status'] ?? null), fn ($query) => $query->where('status', $validated['status']))
            ->when(filled($validated['approval'] ?? null), fn ($query) => $query->where('approval_status', $validated['approval']))
            ->when(filled($validated['type'] ?? null), fn ($query) => $query->where('type', $validated['type']))
            ->when(filled($validated['category_id'] ?? null), fn ($query) => $query->where('category_id', $validated['category_id']))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('vendor.products.index', [
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
            'categories' => $this->categoryOptions(),
            'money' => Money::class,
        ]);
    }

    public function create(): View
    {
        return view('vendor.products.create', $this->formData(new Product([
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'approval_status' => ProductApprovalStatus::Draft,
        ])));
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $product = $createProduct->handle($request->user()->vendor, $request->validated());

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Product draft saved. You can now add product images.');
    }

    public function edit(Request $request, Product $product): View
    {
        Gate::authorize('manage', $product);

        return view('vendor.products.edit', $this->formData($product->load([
            'category.productAttributeSet.attributes.activeValues',
            'brand',
            'galleryImages',
            'variants.attributeValues',
            'variants.inventory',
            'variants.images',
            'specifications.values',
            'specifications.attribute.activeValues',
        ])));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        Gate::authorize('manage', $product);
        $updateProduct->handle($request->user()->vendor, $product, $request->validated());

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Product draft saved.');
    }

    public function destroy(Product $product, ArchiveProduct $archiveProduct): RedirectResponse
    {
        Gate::authorize('manage', $product);
        $archiveProduct->handle($product);

        return redirect()->route('vendor.products.index')->with('status', 'Product archived.');
    }

    public function submit(Product $product, SubmitProductForApproval $submitProduct): RedirectResponse
    {
        Gate::authorize('manage', $product);

        try {
            $submitProduct->handle($product);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('vendor.products.edit', $product)
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Product submitted for marketplace review.');
    }

    public function activate(Product $product, ActivateProduct $activateProduct): RedirectResponse
    {
        Gate::authorize('manage', $product);
        $activateProduct->handle($product);

        return redirect()->route('vendor.products.index')->with('status', 'Product activated.');
    }

    public function deactivate(Product $product, DeactivateProduct $deactivateProduct): RedirectResponse
    {
        Gate::authorize('manage', $product);
        $deactivateProduct->handle($product);

        return redirect()->route('vendor.products.index')->with('status', 'Product deactivated.');
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'types' => ProductType::cases(),
            'money' => Money::class,
            'mediaConfig' => config('marketplace.product_media'),
        ];
    }

    private function categoryOptions()
    {
        return Category::query()
            ->with('parent.parent.parent')
            ->withCount('children')
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (Category $category): bool => $category->children_count === 0 && $category->isPubliclyUsable())
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $this->categoryLabel($category),
            ])
            ->values();
    }

    private function categoryLabel(Category $category): string
    {
        $names = collect([$category->name]);
        $parent = $category->parent;

        while ($parent) {
            $names->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $names->implode(' / ');
    }
}
