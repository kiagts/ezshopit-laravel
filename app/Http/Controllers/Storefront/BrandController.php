<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductDiscoveryRequest;
use App\Models\Brand;
use App\Services\Storefront\ProductDiscoveryService;
use App\Services\Storefront\WishlistStateService;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function __invoke(
        ProductDiscoveryRequest $request,
        Brand $brand,
        ProductDiscoveryService $discovery,
        WishlistStateService $wishlistState
    ): View {
        abort_unless(
            $brand->is_active && is_null($brand->deleted_at),
            404
        );

        $result = $discovery->discover(
            $request->discoveryFilters(),
            brand: $brand
        );

        $wishlistedProductIds = $wishlistState->idsForProducts(
            auth()->user(),
            $result['products']->getCollection()
        );

        return view('storefront.discovery', array_merge($result, [
            'mode' => 'brand',
            'brand' => $brand,
            'navigationBrands' => $this->navigationBrands(),
            'wishlistedProductIds' => $wishlistedProductIds,
        ]));
    }

    private function navigationBrands()
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();
    }
}