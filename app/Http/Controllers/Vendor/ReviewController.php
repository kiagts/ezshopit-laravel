<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ProductReviewStatus::class)],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $vendor = $request->user()->vendor;

        return view('vendor.reviews.index', [
            'reviews' => ProductReview::query()
                ->with(['product:id,name,slug,vendor_id', 'user:id,name'])
                ->whereHas('product', fn (Builder $query) => $query->where('vendor_id', $vendor->id))
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($validated['rating'] ?? null, fn (Builder $query, int $rating) => $query->where('rating', $rating))
                ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('product', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
                ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '>=', $date))
                ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '<=', $date))
                ->latest('submitted_at')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => ProductReviewStatus::cases(),
            'filters' => [
                'status' => $validated['status'] ?? '',
                'rating' => $validated['rating'] ?? '',
                'search' => $validated['search'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
        ]);
    }
}
