<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ProductAttributes\CreateProductAttributeValue;
use App\Actions\ProductAttributes\DeleteProductAttributeValue;
use App\Actions\ProductAttributes\UpdateProductAttributeValue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAttributes\StoreProductAttributeValueRequest;
use App\Http\Requests\Admin\ProductAttributes\UpdateProductAttributeValueRequest;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Http\RedirectResponse;

class ProductAttributeValueController extends Controller
{
    public function store(
        StoreProductAttributeValueRequest $request,
        ProductAttribute $attribute,
        CreateProductAttributeValue $createValue,
    ): RedirectResponse {
        abort_unless($attribute->usesPredefinedValues(), 403);

        $createValue->handle($attribute, $request);

        return redirect()->route('admin.attributes.edit', $attribute)->with('status', 'Attribute value added.');
    }

    public function update(
        UpdateProductAttributeValueRequest $request,
        ProductAttribute $attribute,
        ProductAttributeValue $value,
        UpdateProductAttributeValue $updateValue,
    ): RedirectResponse {
        $this->ensureValueBelongsToAttribute($attribute, $value);
        abort_unless($attribute->usesPredefinedValues(), 403);

        $updateValue->handle($value, $request);

        return redirect()->route('admin.attributes.edit', $attribute)->with('status', 'Attribute value updated.');
    }

    public function destroy(
        ProductAttribute $attribute,
        ProductAttributeValue $value,
        DeleteProductAttributeValue $deleteValue,
    ): RedirectResponse {
        $this->ensureValueBelongsToAttribute($attribute, $value);

        $deleteValue->handle($value);

        return redirect()->route('admin.attributes.edit', $attribute)->with('status', 'Attribute value deleted.');
    }

    private function ensureValueBelongsToAttribute(ProductAttribute $attribute, ProductAttributeValue $value): void
    {
        abort_unless($value->product_attribute_id === $attribute->id, 404);
    }
}
