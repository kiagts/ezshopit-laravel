<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderCancellationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid {--chunk=100 : Number of orders to inspect per batch}';

    protected $description = 'Cancel expired unpaid online card orders and release their reservations.';

    public function handle(CancelOrder $cancelOrder): int
    {
        $expired = 0;
        $skipped = 0;
        $failed = 0;
        $chunkSize = max(1, (int) $this->option('chunk'));

        $this->eligibleQuery()
            ->select('orders.*')
            ->chunkById($chunkSize, function ($orders) use ($cancelOrder, &$expired, &$skipped, &$failed): void {
                foreach ($orders as $order) {
                    try {
                        $before = $order->fresh('payment');
                        $cancelOrder->handle($order, OrderCancellationSource::PaymentExpired);

                        if ($before?->status === OrderStatus::Cancelled) {
                            $skipped++;
                        } else {
                            $expired++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::warning('Expired unpaid order cancellation failed.', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->line("Expired unpaid orders: {$expired}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }

    private function eligibleQuery(): Builder
    {
        return Order::query()
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Completed->value])
            ->whereDoesntHave('vendorOrders', fn (Builder $query) => $query->whereIn('status', [
                VendorOrderStatus::Shipped->value,
                VendorOrderStatus::Delivered->value,
            ]))
            ->whereHas('payment', fn (Builder $query) => $query
                ->where('method', PaymentMethod::Card->value)
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()));
    }
}
