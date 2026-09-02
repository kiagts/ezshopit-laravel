<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reviews\ApproveProductReview;
use App\Actions\Reviews\RejectProductReview;
use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ProductReviewStatus::class)],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return view('admin.reviews.index', [
            'reviews' => ProductReview::query()
                ->with(['product.vendor', 'user'])
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($validated['rating'] ?? null, fn (Builder $query, int $rating) => $query->where('rating', $rating))
                ->when($validated['vendor_id'] ?? null, fn (Builder $query, int $vendorId) => $query->whereHas('product', fn (Builder $query) => $query->where('vendor_id', $vendorId)))
                ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhereHas('product', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                }))
                ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '>=', $date))
                ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '<=', $date))
                ->latest('submitted_at')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => ProductReviewStatus::cases(),
            'vendors' => Vendor::query()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => [
                'status' => $validated['status'] ?? '',
                'rating' => $validated['rating'] ?? '',
                'vendor_id' => $validated['vendor_id'] ?? '',
                'search' => $validated['search'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
        ]);
    }

    public function show(ProductReview $review): View
    {
        return view('admin.reviews.show', [
            'review' => $review->load(['product.vendor', 'user', 'orderItem.vendorOrder.order']),
        ]);
    }

    public function approve(Request $request, ProductReview $review, ApproveProductReview $approve): RedirectResponse
    {
        $approve->handle($review, $request->user());

        return back()->with('status', 'Review approved.');
    }

    public function reject(Request $request, ProductReview $review, RejectProductReview $reject): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reject->handle($review, $request->user(), $validated['reason']);

        return back()->with('status', 'Review rejected.');
    }
}
