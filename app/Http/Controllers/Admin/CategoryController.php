<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\ProductAttributeSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $categories = Category::query()
            ->with(['parent', 'productAttributeSet'])
            ->withCount('children')
            ->search($validated['q'] ?? null)
            ->when(($validated['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($validated['parent_id'] ?? null, fn ($query, $parentId) => $query->where('parent_id', $parentId))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'depths' => $this->depthMap(),
            'parentOptions' => $this->categoryOptions(),
            'attributeSetOptions' => $this->attributeSetOptions(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'parent_id' => $validated['parent_id'] ?? '',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new Category(['is_active' => true, 'sort_order' => 0]),
            'parentOptions' => $this->categoryOptions(),
            'attributeSetOptions' => $this->attributeSetOptions(),
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $category = $createCategory->handle($request);

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            'parentOptions' => $this->categoryOptions($category),
            'attributeSetOptions' => $this->attributeSetOptions($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        $updateCategory->handle($category, $request);

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category updated.');
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        try {
            $deleteCategory->handle($category);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categoryOptions(?Category $excludedCategory = null): Collection
    {
        $categories = Category::query()
            ->select(['id', 'parent_id', 'name'])
            ->ordered()
            ->get();

        $excludedIds = $excludedCategory
            ? $excludedCategory->descendantIds()->push($excludedCategory->id)
            : collect();

        return $this->flattenOptions($categories, null, $excludedIds, 0, collect());
    }

    /**
     * @return Collection<int, ProductAttributeSet>
     */
    private function attributeSetOptions(?Category $category = null): Collection
    {
        return ProductAttributeSet::query()
            ->where(function ($query) use ($category): void {
                $query->where('is_active', true)
                    ->when($category?->product_attribute_set_id, fn ($query, int $setId) => $query->orWhere('id', $setId));
            })
            ->ordered()
            ->get();
    }

    /**
     * @param Collection<int, Category> $categories
     * @param Collection<int, int> $excludedIds
     * @return Collection<int, array{id: int, label: string}>
     */
    private function flattenOptions(Collection $categories, ?int $parentId, Collection $excludedIds, int $depth = 0, ?Collection $visited = null): Collection
    {
        $visited ??= collect();

        return $categories
            ->where('parent_id', $parentId)
            ->reject(fn (Category $category): bool => $excludedIds->contains($category->id) || $visited->contains($category->id))
            ->flatMap(function (Category $category) use ($categories, $excludedIds, $depth, $visited): Collection {
                $label = str_repeat('- ', $depth).$category->name;
                $nextVisited = $visited->merge([$category->id]);

                return collect([['id' => $category->id, 'label' => $label]])
                    ->merge($this->flattenOptions($categories, $category->id, $excludedIds, $depth + 1, $nextVisited));
            })
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function depthMap(): array
    {
        $categories = Category::query()
            ->select(['id', 'parent_id'])
            ->get()
            ->keyBy('id');

        $depths = [];

        foreach ($categories as $category) {
            $depth = 0;
            $seen = [];
            $parentId = $category->parent_id;

            while ($parentId && isset($categories[$parentId]) && ! isset($seen[$parentId])) {
                $seen[$parentId] = true;
                $depth++;
                $parentId = $categories[$parentId]->parent_id;
            }

            $depths[$category->id] = $depth;
        }

        return $depths;
    }
}
