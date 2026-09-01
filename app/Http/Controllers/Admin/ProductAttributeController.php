<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ProductAttributes\CreateProductAttribute;
use App\Actions\ProductAttributes\DeleteProductAttribute;
use App\Actions\ProductAttributes\UpdateProductAttribute;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAttributes\StoreProductAttributeRequest;
use App\Http\Requests\Admin\ProductAttributes\UpdateProductAttributeRequest;
use App\Models\ProductAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductAttributeController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(ProductAttributeType::class)],
            'input_type' => ['nullable', Rule::enum(ProductAttributeInputType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $attributes = ProductAttribute::query()
            ->withCount('values')
            ->search($validated['q'] ?? null)
            ->when(filled($validated['type'] ?? null), fn ($query) => $query->where('type', $validated['type']))
            ->when(filled($validated['input_type'] ?? null), fn ($query) => $query->where('input_type', $validated['input_type']))
            ->when(($validated['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.product-attributes.index', [
            'attributes' => $attributes,
            'types' => ProductAttributeType::cases(),
            'inputTypes' => ProductAttributeInputType::cases(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'input_type' => $validated['input_type'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.product-attributes.create', [
            'attribute' => new ProductAttribute([
                'type' => ProductAttributeType::Specification,
                'input_type' => ProductAttributeInputType::Text,
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'types' => ProductAttributeType::cases(),
            'inputTypes' => ProductAttributeInputType::cases(),
        ]);
    }

    public function store(StoreProductAttributeRequest $request, CreateProductAttribute $createAttribute): RedirectResponse
    {
        $attribute = $createAttribute->handle($request);

        return redirect()->route('admin.attributes.edit', $attribute)->with('status', 'Product attribute created.');
    }

    public function edit(ProductAttribute $attribute): View
    {
        $attribute->load(['values']);

        return view('admin.product-attributes.edit', [
            'attribute' => $attribute,
            'types' => ProductAttributeType::cases(),
            'inputTypes' => ProductAttributeInputType::cases(),
        ]);
    }

    public function update(UpdateProductAttributeRequest $request, ProductAttribute $attribute, UpdateProductAttribute $updateAttribute): RedirectResponse
    {
        $updateAttribute->handle($attribute, $request);

        return redirect()->route('admin.attributes.edit', $attribute)->with('status', 'Product attribute updated.');
    }

    public function destroy(ProductAttribute $attribute, DeleteProductAttribute $deleteAttribute): RedirectResponse
    {
        try {
            $deleteAttribute->handle($attribute);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.attributes.index')->with('status', 'Product attribute deleted.');
    }
}
