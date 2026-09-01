<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Cart\CartResolver;
use App\Services\Cart\EvaluateCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CartResolver $cartResolver, EvaluateCart $evaluateCart): View
    {
        return view('storefront.cart.index', array_merge($evaluateCart->evaluate($cartResolver->current($request)), [
            'navigationCategories' => $this->navigationCategories(),
            'maxQuantity' => max(1, (int) config('marketplace.cart.max_quantity_per_item', 20)),
        ]));
    }

    public function clear(Request $request, CartResolver $cartResolver): RedirectResponse
    {
        $cart = $cartResolver->current($request);
        $cart?->items()->delete();

        return back()->with('status', 'Cart cleared.');
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
