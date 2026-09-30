<?php

namespace App\Services\VendorFinance;

use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VendorFinanceService
{
    public function __construct(private VendorPayoutBalanceService $payouts) {}

    public function currencies(Vendor $vendor): array
    {
        return VendorLedgerEntry::where('vendor_id', $vendor->id)->select('currency')->union(VendorPayout::where('vendor_id', $vendor->id)->select('currency'))->union(VendorOrder::where('vendor_id', $vendor->id)->join('orders', 'orders.id', '=', 'vendor_orders.order_id')->select('orders.currency'))->orderBy('currency')->pluck('currency')->all() ?: [Money::currencyCode()];
    }

    public function ledger(Vendor $vendor, string $currency): Builder
    {
        return VendorLedgerEntry::where('vendor_id', $vendor->id)->where('currency', $currency);
    }

    public function summary(Vendor $vendor, string $currency): array
    {
        $sums = $this->ledger($vendor, $currency)->selectRaw('type, SUM(amount) AS total')->groupBy('type')->pluck('total', 'type')->map(fn ($v) => (int) $v)->all();
        $balance = $this->payouts->summaryFor($vendor, $currency);

        return ['gross_sales' => $sums['sale'] ?? 0, 'vendor_discounts' => -($sums['promotion'] ?? 0), 'commission' => -($sums['commission'] ?? 0), 'refunds' => -($sums['refund'] ?? 0), 'commission_reversed' => $sums['commission_refund'] ?? 0, 'net_earnings' => array_sum($sums) - ($sums['payout'] ?? 0), 'paid_payouts' => -($sums['payout'] ?? 0), 'ledger_balance' => array_sum($sums), 'available_ledger' => $balance->availableLedgerBalance, 'held_balance' => array_sum($sums) - $balance->availableLedgerBalance, 'reserved' => $balance->activePayoutReserved, 'available_payout' => $balance->requestableBalance];
    }

    public function filter(Builder $q, array $filters): Builder
    {
        if (! empty($filters['date_from'])) {
            $q->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $q->where('created_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());
        }
        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['reference'])) {
            $like = '%'.$filters['reference'].'%';
            $q->where(fn ($q) => $q->where('source_key', 'like', $like)->orWhereHas('vendorOrder', fn ($q) => $q->where('vendor_order_number', 'like', $like))->orWhereHas('vendorPayout', fn ($q) => $q->where('payout_number', 'like', $like)));
        }

        return $q;
    }

    public function transactions(Vendor $vendor, string $currency, array $filters): Builder
    {
        // Compute the real all-time running balance before filtering or pagination.
        $window = $this->ledger($vendor, $currency)->select('vendor_ledger_entries.*')->selectRaw('SUM(amount) OVER (ORDER BY created_at, id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_balance');

        return $this->filter(VendorLedgerEntry::query()->fromSub($window, 'vendor_ledger_entries'), $filters)->with(['vendorOrder:id,vendor_id,vendor_order_number', 'vendorPayout:id,vendor_id,payout_number', 'refund:id,refund_number'])->orderBy('created_at')->orderBy('id');
    }

    public function statement(Vendor $vendor, string $currency, array $filters): array
    {
        $base = $this->ledger($vendor, $currency);
        $opening = empty($filters['date_from']) ? 0 : (int) (clone $base)->where('created_at', '<', Carbon::parse($filters['date_from'])->startOfDay())->sum('amount');
        $period = (int) $this->filter(clone $base, array_intersect_key($filters, array_flip(['date_from', 'date_to'])))->sum('amount');

        return ['opening' => $opening, 'period_activity' => $period, 'closing' => $opening + $period, 'filtered_activity' => (int) $this->filter(clone $base, $filters)->sum('amount')];
    }

    public function earnings(Vendor $vendor, string $currency, array $filters): Builder
    {
        $q = VendorOrder::where('vendor_id', $vendor->id)->whereHas('order', fn ($q) => $q->where('currency', $currency))->with(['order:id,currency,placed_at', 'commission'])->withSum(['ledgerEntries as ledger_net' => fn ($q) => $q->where('currency', $currency)], 'amount');
        foreach (['sale', 'promotion', 'commission', 'refund', 'commission_refund'] as $type) {
            $q->withSum(['ledgerEntries as '.$type.'_total' => fn ($q) => $q->where('type', $type)->where('currency', $currency)], 'amount');
        }
        if (! empty($filters['date_from'])) {
            $q->whereHas('order', fn ($q) => $q->where('placed_at', '>=', Carbon::parse($filters['date_from'])->startOfDay()));
        }
        if (! empty($filters['date_to'])) {
            $q->whereHas('order', fn ($q) => $q->where('placed_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay()));
        }
        if (! empty($filters['reference'])) {
            $q->where('vendor_order_number', 'like', '%'.$filters['reference'].'%');
        }

        return $q->orderByDesc('id');
    }

    public function csvText(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$value) ? "'".$value : $value;
    }
}
