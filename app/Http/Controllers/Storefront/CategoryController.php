<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductDiscoveryRequest;
use App\Models\Category;
use App\Services\Storefront\ProductDiscoveryService;
use App\Services\Storefront\WishlistStateService;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __invoke(ProductDiscoveryRequest $request, Category $category, ProductDiscoveryService $discovery, WishlistStateService $wishlistState): View
    {
        abort_unless($discovery->categoryIsPublic($category), 404);

        $category->loadMissing('parent.parent.parent', 'productAttributeSet');

        $children = $discovery->activePublicChildren($category);
        $result = $discovery->discover($request->discoveryFilters(), $category);
        $wishlistedProductIds = $wishlistState->idsForProducts(auth()->user(), $result['products']->getCollection());

        return view('storefront.discovery', array_merge($result, [
            'mode' => 'category',
            'category' => $category,
            'breadcrumbs' => $discovery->breadcrumbs($category),
            'children' => $children,
            'navigationCategories' => $this->navigationCategories(),
            'wishlistedProductIds' => $wishlistedProductIds,
        ]));
    }

    private function navigationCategories()
    {
        return Category::query()
            ->roots()
            ->active()
            ->with(['activeChildren' => fn ($query) => $query->ordered()->limit(6)])
            ->ordered()
            ->limit(8)
            ->get();
    }
}
