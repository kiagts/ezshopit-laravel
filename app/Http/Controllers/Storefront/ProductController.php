<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\StorefrontProductService;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product, StorefrontProductService $products): View
    {
        return view('storefront.products.show', $products->detail($product));
    }
}
