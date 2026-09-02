<?php

namespace App\Http\Controllers\Account;

use App\Actions\Reviews\CreateProductReview;
use App\Actions\Reviews\UpdateProductReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreProductReviewRequest;
use App\Http\Requests\Account\UpdateProductReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Services\Reviews\ReviewEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request, ReviewEligibilityService $eligibility): View
    {
        return view('account.reviews.index', [
            'awaitingReviewItems' => $eligibility->awaitingReviewItems($request->user()),
            'reviews' => $request->user()->productReviews()
                ->with(['product.primaryImage', 'product.firstGalleryImage'])
                ->latest('submitted_at')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, OrderItem $orderItem, ReviewEligibilityService $eligibility): View|RedirectResponse
    {
        abort_unless((int) $orderItem->vendorOrder?->order?->user_id === (int) $request->user()->id, 404);

        if ($existing = $eligibility->existingReviewFor($request->user(), $orderItem)) {
            return redirect()->route('account.reviews.edit', $existing);
        }

        abort_unless($eligibility->canReview($request->user(), $orderItem), 403);

        return view('account.reviews.create', [
            'orderItem' => $orderItem->load(['product.primaryImage', 'product.firstGalleryImage', 'vendorOrder.order']),
        ]);
    }

    public function store(StoreProductReviewRequest $request, OrderItem $orderItem, CreateProductReview $createReview): RedirectResponse
    {
        abort_unless((int) $orderItem->vendorOrder?->order?->user_id === (int) $request->user()->id, 404);

        $review = $createReview->handle($request->user(), $orderItem, $request->validated());

        return redirect()
            ->route('account.reviews.edit', $review)
            ->with('status', 'Your review was submitted for moderation.');
    }

    public function edit(Request $request, ProductReview $review): View
    {
        abort_unless((int) $review->user_id === (int) $request->user()->id, 404);

        return view('account.reviews.edit', [
            'review' => $review->load(['product.primaryImage', 'product.firstGalleryImage', 'orderItem.vendorOrder.order']),
        ]);
    }

    public function update(UpdateProductReviewRequest $request, ProductReview $review, UpdateProductReview $updateReview): RedirectResponse
    {
        abort_unless((int) $review->user_id === (int) $request->user()->id, 404);

        $updateReview->handle($review, $request->validated());

        return redirect()
            ->route('account.reviews.edit', $review)
            ->with('status', 'Your review was resubmitted for moderation.');
    }
}
