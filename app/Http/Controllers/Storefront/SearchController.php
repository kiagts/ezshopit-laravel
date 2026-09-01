<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductDiscoveryRequest;
use App\Models\Category;
use App\Services\Storefront\ProductDiscoveryService;
use App\Services\Storefront\WishlistStateService;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(ProductDiscoveryRequest $request, ProductDiscoveryService $discovery, WishlistStateService $wishlistState): View
    {
        $filters = $request->discoveryFilters();

        if (blank($filters['q'])) {
            $filters['q'] = '';
        }

        $result = filled($filters['q'])
            ? $discovery->discover($filters)
            : [
                'products' => null,
                'filters' => $filters,
                'sortOptions' => ['featured' => 'Featured', 'newest' => 'Newest', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'name_asc' => 'Name: A-Z'],
                'brandOptions' => collect(),
                'categoryOptions' => collect(),
                'attributeFilters' => collect(),
                'activeFilters' => collect(),
                'activeFilterCount' => 0,
            ];
        $wishlistedProductIds = $result['products']
            ? $wishlistState->idsForProducts(auth()->user(), $result['products']->getCollection())
            : [];

        return view('storefront.discovery', array_merge($result, [
            'mode' => 'search',
            'category' => null,
            'breadcrumbs' => collect(),
            'children' => collect(),
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
