<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductDiscoveryRequest;
use App\Models\Category;
use App\Models\Vendor;
use App\Services\Storefront\ProductDiscoveryService;
use App\Services\Storefront\WishlistStateService;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function __invoke(ProductDiscoveryRequest $request, Vendor $vendor, ProductDiscoveryService $discovery, WishlistStateService $wishlistState): View
    {
        abort_unless($vendor->isApproved(), 404);

        $result = $discovery->discover($request->discoveryFilters(), vendor: $vendor);
        $wishlistedProductIds = $wishlistState->idsForProducts(auth()->user(), $result['products']->getCollection());

        return view('storefront.stores.show', array_merge($result, [
            'vendor' => $vendor,
            'storeCategories' => $discovery->vendorCategories($vendor),
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
