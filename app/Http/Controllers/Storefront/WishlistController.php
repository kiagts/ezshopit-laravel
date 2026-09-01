<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Services\Storefront\WishlistStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request, WishlistStateService $wishlistState): View
    {
        $items = WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'product.vendor:id,display_name,slug',
                'product.primaryImage',
                'product.firstGalleryImage',
                'product.variants' => fn ($query) => $query->active()->with('inventory')->orderBy('sort_order')->orderBy('sku'),
            ])
            ->latest('created_at')
            ->paginate(24)
            ->withQueryString();

        return view('account.wishlist', [
            'items' => $items,
            'publicProductIds' => $wishlistState->publicProductIdsForItems($items->getCollection()),
            'navigationCategories' => $this->navigationCategories(),
        ]);
    }

    public function store(Request $request, string $product): RedirectResponse
    {
        abort_unless(ctype_digit($product), 404);

        abort_unless(Product::query()->publiclyVisible()->whereKey($product)->exists(), 404);

        WishlistItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => (int) $product,
        ]);

        return back()->with('status', 'Saved to wishlist.');
    }

    public function destroy(Request $request, string $product): RedirectResponse
    {
        abort_unless(ctype_digit($product), 404);

        WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', (int) $product)
            ->delete();

        return back()->with('status', 'Removed from wishlist.');
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
