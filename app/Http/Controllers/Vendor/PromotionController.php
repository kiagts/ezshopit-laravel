<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFundingSource;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Promotions\PromotionCode;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor;

        return view('vendor.promotions.index', [
            'promotions' => $vendor->promotions()
                ->withCount('redemptions')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        return view('vendor.promotions.create', $this->formData($request, new Promotion(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        $data = $this->validated($request);
        $promotion = $vendor->promotions()->create($data + [
            'funding_source' => PromotionFundingSource::Vendor,
            'created_by' => $request->user()->id,
        ]);
        $this->syncTargets($promotion, $request, $vendor->id);

        return redirect()->route('vendor.promotions.index')->with('status', 'Promotion created.');
    }

    public function edit(Request $request, Promotion $promotion): View
    {
        abort_unless((int) $promotion->vendor_id === (int) $request->user()->vendor->id, 404);

        return view('vendor.promotions.edit', $this->formData($request, $promotion->load(['products', 'categories'])));
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless((int) $promotion->vendor_id === (int) $vendor->id, 404);

        $promotion->update($this->validated($request, $promotion) + ['updated_by' => $request->user()->id]);
        $this->syncTargets($promotion, $request, $vendor->id);

        return redirect()->route('vendor.promotions.index')->with('status', 'Promotion updated.');
    }

    private function validated(Request $request, ?Promotion $promotion = null): array
    {
        $request->merge(['code' => PromotionCode::normalize($request->input('code'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', Rule::unique('promotions', 'code')->ignore($promotion)],
            'discount_type' => ['required', Rule::enum(PromotionDiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'minimum_spend' => ['nullable', 'string'],
            'maximum_discount' => ['nullable', 'string'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $data['discount_value'] = $data['discount_type'] === PromotionDiscountType::Percentage->value
                ? max(1, min(10000, (int) round(((float) $data['discount_value']) * 100)))
                : Money::parseDecimal($data['discount_value']);
            $data['minimum_spend'] = filled($data['minimum_spend'] ?? null) ? Money::parseDecimal($data['minimum_spend']) : null;
            $data['maximum_discount'] = filled($data['maximum_discount'] ?? null) ? Money::parseDecimal($data['maximum_discount']) : null;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['discount_value' => $exception->getMessage()]);
        }
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function syncTargets(Promotion $promotion, Request $request, int $vendorId): void
    {
        $productIds = Product::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('id', $request->collect('product_ids')->map(fn ($id): int => (int) $id)->filter()->all())
            ->pluck('id')
            ->all();

        $promotion->products()->sync($productIds);
        $promotion->categories()->sync($request->collect('category_ids')->map(fn ($id): int => (int) $id)->filter()->unique()->all());
    }

    private function formData(Request $request, Promotion $promotion): array
    {
        $vendor = $request->user()->vendor;

        return [
            'promotion' => $promotion,
            'products' => $vendor->products()->orderBy('name')->get(['id', 'vendor_id', 'name']),
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'discountTypes' => PromotionDiscountType::cases(),
        ];
    }
}
