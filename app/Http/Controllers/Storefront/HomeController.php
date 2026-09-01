<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Storefront\WishlistStateService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(WishlistStateService $wishlistState): View
    {
        $navigationCategories = Category::query()
            ->roots()
            ->active()
            ->with(['activeChildren' => fn ($query) => $query->ordered()->limit(6)])
            ->ordered()
            ->limit(8)
            ->get();

        $featuredProducts = $this->products()
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(8)
            ->get();

        $newArrivals = $this->products()
            ->latest('published_at')
            ->latest('id')
            ->limit(8)
            ->get();

        $featuredVendors = Vendor::query()
            ->approved()
            ->orderBy('display_name')
            ->limit(4)
            ->get(['id', 'display_name', 'slug', 'description', 'logo_path', 'banner_path']);

        $wishlistedProductIds = $wishlistState->idsForProducts(
            auth()->user(),
            $featuredProducts->concat($newArrivals),
        );

        return view('storefront.home', [
            'navigationCategories' => $navigationCategories,
            'homepageCategories' => $navigationCategories->take(8),
            'featuredProducts' => $featuredProducts,
            'newArrivals' => $newArrivals,
            'featuredVendors' => $featuredVendors,
            'wishlistedProductIds' => $wishlistedProductIds,
        ]);
    }

    private function products()
    {
        return Product::query()
            ->publiclyVisible()
            ->with([
                'vendor:id,display_name,slug',
                'primaryImage',
                'firstGalleryImage',
                'variants' => fn ($query) => $query->active()->with('inventory')->orderBy('sort_order')->orderBy('sku'),
            ]);
    }
}
