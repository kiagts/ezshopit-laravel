<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFundingSource;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Vendor;
use App\Services\Promotions\PromotionCode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
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
        $filters = [
            'q' => $request->string('q')->toString(),
            'funding_source' => $request->string('funding_source')->toString(),
            'vendor_id' => $request->integer('vendor_id') ?: null,
            'status' => $request->string('status')->toString(),
        ];

        return view('admin.promotions.index', [
            'promotions' => Promotion::query()
                ->with('vendor')
                ->withCount('redemptions')
                ->search($filters['q'])
                ->when($filters['funding_source'], fn (Builder $query, string $source) => $query->where('funding_source', $source))
                ->when($filters['vendor_id'], fn (Builder $query, int $vendorId) => $query->where('vendor_id', $vendorId))
                ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn (Builder $query) => $query->where('is_active', false))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'vendors' => Vendor::query()->approved()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.promotions.create', $this->formData(new Promotion(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $promotion = Promotion::query()->create($data + ['created_by' => $request->user()->id]);
        $this->syncTargets($promotion, $request);

        return redirect()->route('admin.promotions.index')->with('status', 'Promotion created.');
    }

    public function edit(Promotion $promotion): View
    {
        return view('admin.promotions.edit', $this->formData($promotion->load(['products', 'categories'])));
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $data = $this->validated($request, $promotion);
        $promotion->update($data + ['updated_by' => $request->user()->id]);
        $this->syncTargets($promotion, $request);

        return redirect()->route('admin.promotions.index')->with('status', 'Promotion updated.');
    }

    private function validated(Request $request, ?Promotion $promotion = null): array
    {
        $request->merge(['code' => PromotionCode::normalize($request->input('code'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', Rule::unique('promotions', 'code')->ignore($promotion)],
            'funding_source' => ['required', Rule::enum(PromotionFundingSource::class)],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
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

        if ($data['funding_source'] === PromotionFundingSource::Vendor->value && empty($data['vendor_id'])) {
            throw ValidationException::withMessages(['vendor_id' => 'Vendor-funded promotions require a vendor.']);
        }

        if ($data['funding_source'] === PromotionFundingSource::Marketplace->value) {
            $data['vendor_id'] = null;
        }

        try {
            $data['discount_value'] = $this->discountValue($data['discount_type'], $data['discount_value']);
            $data['minimum_spend'] = filled($data['minimum_spend'] ?? null) ? Money::parseDecimal($data['minimum_spend']) : null;
            $data['maximum_discount'] = filled($data['maximum_discount'] ?? null) ? Money::parseDecimal($data['maximum_discount']) : null;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['discount_value' => $exception->getMessage()]);
        }
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function discountValue(string $type, string $value): int
    {
        if ($type === PromotionDiscountType::Percentage->value) {
            return max(1, min(10000, (int) round(((float) $value) * 100)));
        }

        return Money::parseDecimal($value);
    }

    private function syncTargets(Promotion $promotion, Request $request): void
    {
        $promotion->products()->sync($request->collect('product_ids')->map(fn ($id): int => (int) $id)->filter()->unique()->all());
        $promotion->categories()->sync($request->collect('category_ids')->map(fn ($id): int => (int) $id)->filter()->unique()->all());
    }

    private function formData(Promotion $promotion): array
    {
        return [
            'promotion' => $promotion,
            'vendors' => Vendor::query()->approved()->orderBy('display_name')->get(['id', 'display_name']),
            'products' => Product::query()->with('vendor')->orderBy('name')->limit(200)->get(['id', 'vendor_id', 'name']),
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'fundingSources' => PromotionFundingSource::cases(),
            'discountTypes' => PromotionDiscountType::cases(),
        ];
    }
}
