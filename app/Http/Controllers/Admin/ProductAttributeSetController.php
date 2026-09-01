<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ProductAttributeSets\CreateProductAttributeSet;
use App\Actions\ProductAttributeSets\DeleteProductAttributeSet;
use App\Actions\ProductAttributeSets\UpdateProductAttributeSet;
use App\Enums\ProductAttributeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAttributeSets\StoreProductAttributeSetRequest;
use App\Http\Requests\Admin\ProductAttributeSets\UpdateProductAttributeSetRequest;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductAttributeSetController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $attributeSets = ProductAttributeSet::query()
            ->withCount([
                'attributes',
                'categories',
                'attributes as variant_attributes_count' => fn ($query) => $query->where('product_attributes.type', ProductAttributeType::Variant->value),
                'attributes as specification_attributes_count' => fn ($query) => $query->where('product_attributes.type', ProductAttributeType::Specification->value),
            ])
            ->search($validated['q'] ?? null)
            ->when(($validated['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.product-attribute-sets.index', [
            'attributeSets' => $attributeSets,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.product-attribute-sets.create', [
            'attributeSet' => new ProductAttributeSet(['is_active' => true, 'sort_order' => 0]),
            'attributes' => $this->availableAttributes(),
            'assignments' => [],
        ]);
    }

    public function store(StoreProductAttributeSetRequest $request, CreateProductAttributeSet $createSet): RedirectResponse
    {
        $attributeSet = $createSet->handle($request);

        return redirect()->route('admin.attribute-sets.edit', $attributeSet)->with('status', 'Attribute set created.');
    }

    public function edit(ProductAttributeSet $attributeSet): View
    {
        $attributeSet->load('attributes');

        return view('admin.product-attribute-sets.edit', [
            'attributeSet' => $attributeSet,
            'attributes' => $this->availableAttributes($attributeSet),
            'assignments' => $this->assignmentMap($attributeSet),
        ]);
    }

    public function update(UpdateProductAttributeSetRequest $request, ProductAttributeSet $attributeSet, UpdateProductAttributeSet $updateSet): RedirectResponse
    {
        $updateSet->handle($attributeSet, $request);

        return redirect()->route('admin.attribute-sets.edit', $attributeSet)->with('status', 'Attribute set updated.');
    }

    public function destroy(ProductAttributeSet $attributeSet, DeleteProductAttributeSet $deleteSet): RedirectResponse
    {
        try {
            $deleteSet->handle($attributeSet);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.attribute-sets.index')->with('status', 'Attribute set deleted.');
    }

    /**
     * @return Collection<int, ProductAttribute>
     */
    private function availableAttributes(?ProductAttributeSet $attributeSet = null): Collection
    {
        $existingIds = $attributeSet
            ? $attributeSet->attributes()->pluck('product_attributes.id')->all()
            : [];

        return ProductAttribute::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($existingIds): void {
                $query->where('is_active', true)
                    ->when($existingIds !== [], fn ($query) => $query->orWhereIn('id', $existingIds));
            })
            ->ordered()
            ->get();
    }

    /**
     * @return array<int, array{product_attribute_id: int, is_required: bool, sort_order: int}>
     */
    private function assignmentMap(ProductAttributeSet $attributeSet): array
    {
        return $attributeSet->attributes
            ->mapWithKeys(fn (ProductAttribute $attribute): array => [
                $attribute->id => [
                    'product_attribute_id' => $attribute->id,
                    'is_required' => (bool) $attribute->pivot->is_required,
                    'sort_order' => (int) $attribute->pivot->sort_order,
                ],
            ])
            ->all();
    }
}
