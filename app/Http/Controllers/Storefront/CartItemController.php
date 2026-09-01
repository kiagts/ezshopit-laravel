<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddCartItemRequest;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Services\Cart\CartResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function store(AddCartItemRequest $request, AddItemToCart $addItemToCart): RedirectResponse
    {
        $addItemToCart->handle(
            $request,
            (int) $request->validated('product_variant_id'),
            (int) $request->validated('quantity'),
        );

        return back()->with('status', 'Added to cart.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem, CartResolver $cartResolver, UpdateCartItemQuantity $updateQuantity): RedirectResponse
    {
        $cart = $cartResolver->current($request);
        abort_unless($cart && $cartItem->cart_id === $cart->id, 404);

        $updateQuantity->handle($cartItem, (int) $request->validated('quantity'));

        return back()->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem, CartResolver $cartResolver): RedirectResponse
    {
        $cart = $cartResolver->current($request);
        abort_unless($cart && $cartItem->cart_id === $cart->id, 404);

        $cartItem->delete();

        return back()->with('status', 'Removed from cart.');
    }
}
