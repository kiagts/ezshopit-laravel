<?php

namespace App\Services\Account;

use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CustomerAccountDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summaryFor(User $user): array
    {
        return [
            'ordersCount' => $user->orders()->count(),
            'wishlistCount' => $user->wishlistItems()->count(),
            'awaitingReviewsCount' => $this->awaitingReviewCount($user),
            'unreadNotificationsCount' => $user->unreadNotifications()->count(),
            'latestOrders' => $user->orders()
                ->with('payment')
                ->withCount('vendorOrders')
                ->latest('placed_at')
                ->latest()
                ->limit(5)
                ->get(),
            'defaultAddress' => $user->addresses()
                ->where('is_default', true)
                ->latest('updated_at')
                ->first(),
            'latestNotifications' => $user->notifications()
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    private function awaitingReviewCount(User $user): int
    {
        return OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('vendorOrder', function (Builder $query) use ($user): void {
                $query
                    ->where('status', VendorOrderStatus::Delivered)
                    ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id));
            })
            ->whereDoesntHave('product.reviews', fn (Builder $query) => $query->where('user_id', $user->id))
            ->distinct('product_id')
            ->count('product_id');
    }
}
