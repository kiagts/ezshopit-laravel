<?php

namespace App\Http\Controllers\Vendor\Products;

use App\Actions\Products\AddProductImages;
use App\Actions\Products\DeleteProductImage;
use App\Actions\Products\ReorderProductImages;
use App\Actions\Products\SetPrimaryProductImage;
use App\Actions\Products\UpdateProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Products\ReorderProductImagesRequest;
use App\Http\Requests\Vendor\Products\StoreProductImagesRequest;
use App\Http\Requests\Vendor\Products\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductImageController extends Controller
{
    public function store(StoreProductImagesRequest $request, Product $product, AddProductImages $addProductImages): RedirectResponse
    {
        Gate::authorize('manage', $product);

        $variant = filled($request->validated('product_variant_id'))
            ? $product->variants()->whereKey((int) $request->validated('product_variant_id'))->first()
            : null;

        try {
            $addProductImages->handle(
                $product,
                $request->file('images', []),
                $variant,
                $request->validated('alt_text') ?: null,
            );
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.products.edit', $product)->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Product images uploaded.');
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $image, UpdateProductImage $updateProductImage): RedirectResponse
    {
        Gate::authorize('manage', $product);

        try {
            $updateProductImage->handle($product, $image, $request->validated('alt_text') ?: null);
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.products.edit', $product)->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Image details updated.');
    }

    public function destroy(Product $product, ProductImage $image, DeleteProductImage $deleteProductImage): RedirectResponse
    {
        Gate::authorize('manage', $product);

        try {
            $deleteProductImage->handle($product, $image);
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.products.edit', $product)->withErrors($exception->errors());
        }

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Image deleted.');
    }

    public function primary(Product $product, ProductImage $image, SetPrimaryProductImage $setPrimaryProductImage): RedirectResponse
    {
        Gate::authorize('manage', $product);

        try {
            $setPrimaryProductImage->handle($product, $image);
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.products.edit', $product)->withErrors($exception->errors());
        }

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Primary image updated.');
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product, ReorderProductImages $reorderProductImages): RedirectResponse
    {
        Gate::authorize('manage', $product);

        try {
            $reorderProductImages->handle($product, $request->validated('images'));
        } catch (ValidationException $exception) {
            return redirect()->route('vendor.products.edit', $product)->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Image order updated.');
    }
}
