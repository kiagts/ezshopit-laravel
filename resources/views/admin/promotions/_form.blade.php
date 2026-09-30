@php
    use App\Support\Money;

    $selectedProducts = old('product_ids', $promotion->exists ? $promotion->products->pluck('id')->all() : []);
    $selectedCategories = old('category_ids', $promotion->exists ? $promotion->categories->pluck('id')->all() : []);
    $discountValue = old('discount_value', $promotion->exists && $promotion->discount_type?->value === 'percentage' ? $promotion->discount_value / 100 : ($promotion->exists ? Money::input($promotion->discount_value) : ''));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-input name="name" label="Promotion Name" :value="old('name', $promotion->name)" required />
        <x-input name="code" label="Coupon Code" :value="old('code', $promotion->code)" placeholder="SAVE10" required />

        <x-select name="funding_source" label="Funding Source" required>
            @foreach ($fundingSources as $source)
                <option value="{{ $source->value }}" @selected(old('funding_source', $promotion->funding_source?->value) === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-select>

        <x-select name="vendor_id" label="Vendor">
            <option value="">Marketplace funded</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((int) old('vendor_id', $promotion->vendor_id) === $vendor->id)>{{ $vendor->display_name }}</option>
            @endforeach
        </x-select>

        <x-select name="discount_type" label="Discount Type" required>
            @foreach ($discountTypes as $type)
                <option value="{{ $type->value }}" @selected(old('discount_type', $promotion->discount_type?->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-select>

        <x-input name="discount_value" label="Discount Value" :value="$discountValue" placeholder="10 or 25.00" required />
        <x-input name="minimum_spend" label="Minimum Eligible Spend" :value="old('minimum_spend', $promotion->minimum_spend ? Money::input($promotion->minimum_spend) : '')" placeholder="50.00" />
        <x-input name="maximum_discount" label="Maximum Discount" :value="old('maximum_discount', $promotion->maximum_discount ? Money::input($promotion->maximum_discount) : '')" placeholder="25.00" />
        <x-input name="usage_limit" type="number" label="Total Usage Limit" :value="old('usage_limit', $promotion->usage_limit)" min="1" />
        <x-input name="per_customer_limit" type="number" label="Per Customer Limit" :value="old('per_customer_limit', $promotion->per_customer_limit)" min="1" />
        <x-input name="starts_at" type="datetime-local" label="Starts At" :value="old('starts_at', optional($promotion->starts_at)->format('Y-m-d\TH:i'))" />
        <x-input name="ends_at" type="datetime-local" label="Ends At" :value="old('ends_at', optional($promotion->ends_at)->format('Y-m-d\TH:i'))" />
    </div>

    <label class="flex items-center gap-3 text-sm font-medium text-neutral-700">
        <input type="checkbox" name="is_active" value="1" class="rounded border-neutral-300 text-brand-700 focus:ring-brand-700" @checked(old('is_active', $promotion->is_active))>
        Active
    </label>

    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-neutral-700">Product Scope</label>
            <select name="product_ids[]" multiple class="mt-2 min-h-40 w-full rounded-md border-neutral-300 text-sm focus:border-brand-700 focus:ring-brand-700">
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(in_array($product->id, array_map('intval', $selectedProducts), true))>{{ $product->name }} @if($product->vendor) - {{ $product->vendor->display_name }} @endif</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-neutral-700">Category Scope</label>
            <select name="category_ids[]" multiple class="mt-2 min-h-40 w-full rounded-md border-neutral-300 text-sm focus:border-brand-700 focus:ring-brand-700">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array($category->id, array_map('intval', $selectedCategories), true))>{{ $category->name }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-neutral-500">Category coupons include descendant categories.</p>
        </div>
    </div>

    <div class="flex gap-3">
        <x-button type="submit">{{ $submitLabel }}</x-button>
        <x-button :href="route('admin.promotions.index')" variant="secondary">Cancel</x-button>
    </div>
</form>
