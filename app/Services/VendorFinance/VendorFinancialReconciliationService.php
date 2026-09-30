<?php

namespace App\Services\VendorFinance;

use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class VendorFinancialReconciliationService
{
    public function __construct(private VendorFinanceService $finance) {}

    public function inspect(Vendor $vendor, string $currency): array
    {
        $findings = [];
        $checks = [];
        $concat = fn (string $prefix, string $column, string $suffix) => DB::connection()->getDriverName() === 'sqlite' ? "'$prefix' || $column || '$suffix'" : "CONCAT('$prefix', $column, '$suffix')";
        $sources = DB::table('vendor_order_commissions as c')->join('vendor_orders as v', 'v.id', '=', 'c.vendor_order_id')->where('v.vendor_id', $vendor->id)->where('c.currency', $currency);
        $check = function (string $name, $expected) use (&$findings, &$checks) {
            $mismatch = DB::query()->fromSub($expected, 'expected')->leftJoin('vendor_ledger_entries as l', 'l.source_key', '=', 'expected.source_key')->where(fn ($q) => $q->whereNull('l.id')->orWhereColumn('l.amount', '<>', 'expected.amount')->orWhereColumn('l.currency', '<>', 'expected.currency')->orWhereColumn('l.vendor_id', '<>', 'expected.vendor_id')->orWhereColumn('l.type', '<>', 'expected.type'));
            $count = (clone $mismatch)->count();
            $checks[$name] = $count === 0;
            foreach ($mismatch->limit(30)->get(['expected.source_key', 'l.id']) as $row) {
                $findings[] = ['severity' => 'error', 'check' => $name, 'reference' => $row->source_key, 'message' => $row->id ? 'Posted entry differs from its authoritative source.' : 'Expected ledger entry is missing.'];
            }
            if ($count > 30) {
                $findings[] = ['severity' => 'error', 'check' => $name, 'reference' => '', 'message' => ($count - 30).' further mismatches; inspect source records.'];
            }
        };
        $check('sale', (clone $sources)->whereRaw('(v.items_subtotal + v.shipping_total) > 0')->selectRaw($concat('vendor-order:', 'v.id', ':sale')." as source_key, v.vendor_id, c.currency, 'sale' as type, (v.items_subtotal + v.shipping_total) as amount"));
        $check('commission', (clone $sources)->where('c.original_commission_amount', '>', 0)->selectRaw($concat('vendor-order-commission:', 'c.id', ':commission')." as source_key, v.vendor_id, c.currency, 'commission' as type, -c.original_commission_amount as amount"));
        $check('promotion', (clone $sources)->where('v.vendor_discount_total', '>', 0)->selectRaw($concat('vendor-order:', 'v.id', ':promotion')." as source_key, v.vendor_id, c.currency, 'promotion' as type, -v.vendor_discount_total as amount"));
        $refunds = DB::table('refund_allocations as a')->join('refunds as r', 'r.id', '=', 'a.refund_id')->join('vendor_orders as v', 'v.id', '=', 'a.vendor_order_id')->where('v.vendor_id', $vendor->id)->where('r.currency', $currency)->where(fn ($q) => $q->where('r.status', 'succeeded')->orWhereNotNull('r.economics_posted_at'))->where('a.amount', '>', 0)->whereIn('a.type', ['item', 'shipping']);
        $check('refund', $refunds->selectRaw($concat('refund-allocation:', 'a.id', ':refund')." as source_key, v.vendor_id, r.currency, 'refund' as type, -COALESCE(a.seller_amount,a.amount) as amount"));
        $adjustments = DB::table('commission_adjustments as a')->join('vendor_order_commissions as c', 'c.id', '=', 'a.vendor_order_commission_id')->join('vendor_orders as v', 'v.id', '=', 'c.vendor_order_id')->where('v.vendor_id', $vendor->id)->where('c.currency', $currency)->where('a.type', 'refund')->where('a.commission_amount_delta', '<', 0);
        $check('commission_refund', $adjustments->selectRaw($concat('commission-adjustment:', 'a.id', ':commission-refund')." as source_key, v.vendor_id, c.currency, 'commission_refund' as type, -a.commission_amount_delta as amount"));
        $payouts = DB::table('vendor_payouts as p')->where('p.vendor_id', $vendor->id)->where('p.currency', $currency)->where('p.status', 'paid');
        $check('paid_payout', $payouts->selectRaw($concat('vendor-payout:', 'p.id', ':payout')." as source_key, p.vendor_id, p.currency, 'payout' as type, -p.amount as amount"));
        // Reverse checks detect orphaned, duplicate or improperly attributed postings too.
        $ledger = DB::table('vendor_ledger_entries as l')->where('l.vendor_id', $vendor->id)->where('l.currency', $currency);
        $duplicates = (clone $ledger)->select('reference_type', 'reference_id', 'type')->selectRaw('COUNT(*) as total')->whereNotNull('reference_type')->whereNotNull('reference_id')->groupBy('reference_type', 'reference_id', 'type')->havingRaw('COUNT(*) > 1')->limit(30)->get();
        $checks['duplicate_source'] = $duplicates->isEmpty();
        foreach ($duplicates as $d) {
            $findings[] = ['severity' => 'error', 'check' => 'duplicate_source', 'reference' => $d->type.':'.$d->reference_id, 'message' => 'Multiple entries reference the same logical source.'];
        }
        $sourceMap = ['sale' => ['vendor_orders', 'vendor_order_id', 'App\\Models\\VendorOrder'], 'promotion' => ['vendor_orders', 'vendor_order_id', 'App\\Models\\VendorOrder'], 'commission' => ['vendor_order_commissions', 'vendor_order_commission_id', 'App\\Models\\VendorOrderCommission'], 'refund' => ['refund_allocations', 'refund_allocation_id', 'App\\Models\\RefundAllocation'], 'commission_refund' => ['commission_adjustments', 'commission_adjustment_id', 'App\\Models\\CommissionAdjustment'], 'payout' => ['vendor_payouts', 'vendor_payout_id', 'App\\Models\\VendorPayout']];
        foreach ($sourceMap as $type => [$table,$fk,$class]) {
            $bad = (clone $ledger)->leftJoin($table.' as src', 'src.id', '=', 'l.'.$fk)->where('l.type', $type)->where(fn ($q) => $q->whereNull('src.id')->orWhere('l.reference_type', '<>', $class)->orWhereNull('l.reference_type')->orWhereNull('l.reference_id')->orWhereColumn('l.reference_id', '<>', 'src.id'));
            $checks['source_'.$type] = ! (clone $bad)->exists();
            foreach ($bad->limit(30)->get(['l.source_key']) as $row) {
                $findings[] = ['severity' => 'error', 'check' => 'source_'.$type, 'reference' => $row->source_key, 'message' => 'Source reference is missing or inconsistent.'];
            }
        }
        $badPayout = (clone $ledger)->join('vendor_payouts as p', 'p.id', '=', 'l.vendor_payout_id')->where('l.type', 'payout')->where(fn ($q) => $q->where('p.status', '<>', 'paid')->orWhereColumn('p.currency', '<>', 'l.currency')->orWhereColumn('p.vendor_id', '<>', 'l.vendor_id'));
        $checks['unpaid_payout'] = ! (clone $badPayout)->exists();
        foreach ($badPayout->limit(30)->get(['l.source_key']) as $row) {
            $findings[] = ['severity' => 'error', 'check' => 'unpaid_payout', 'reference' => $row->source_key, 'message' => 'Payout deduction has an unpaid or mismatched source.'];
        }
        // A source-currency query alone misses entries incorrectly posted into another currency.
        foreach (['sale', 'promotion', 'commission', 'commission_refund', 'refund'] as $type) {
            $q = (clone $ledger)->where('l.type', $type);
            if (in_array($type, ['sale', 'promotion'], true)) {
                $q->leftJoin('vendor_orders as vo', 'vo.id', '=', 'l.vendor_order_id')->leftJoin('vendor_order_commissions as c', 'c.vendor_order_id', '=', 'vo.id');
                $owner = 'vo.vendor_id';
                $code = 'c.currency';
                $amount = $type === 'sale' ? '(vo.items_subtotal + vo.shipping_total)' : '-vo.vendor_discount_total';
            } elseif ($type === 'commission') {
                $q->leftJoin('vendor_order_commissions as c', 'c.id', '=', 'l.vendor_order_commission_id')->leftJoin('vendor_orders as vo', 'vo.id', '=', 'c.vendor_order_id');
                $owner = 'vo.vendor_id';
                $code = 'c.currency';
                $amount = '-c.original_commission_amount';
            } elseif ($type === 'commission_refund') {
                $q->leftJoin('commission_adjustments as a', 'a.id', '=', 'l.commission_adjustment_id')->leftJoin('vendor_order_commissions as c', 'c.id', '=', 'a.vendor_order_commission_id')->leftJoin('vendor_orders as vo', 'vo.id', '=', 'c.vendor_order_id');
                $owner = 'vo.vendor_id';
                $code = 'c.currency';
                $amount = '-a.commission_amount_delta';
            } else {
                $q->leftJoin('refund_allocations as a', 'a.id', '=', 'l.refund_allocation_id')->leftJoin('refunds as r', 'r.id', '=', 'a.refund_id')->leftJoin('vendor_orders as vo', 'vo.id', '=', 'a.vendor_order_id');
                $owner = 'vo.vendor_id';
                $code = 'r.currency';
                $amount = '-COALESCE(a.seller_amount,a.amount)';
            }
            $q->where(function ($q) use ($owner, $code, $amount, $type) {
                $q->whereNull($owner)->orWhereNull($code)->orWhereColumn('l.vendor_id', '<>', $owner)->orWhereColumn('l.currency', '<>', $code)->orWhereRaw('l.amount <> '.$amount)->orWhereColumn('l.vendor_order_id', '<>', 'vo.id');
                if ($type === 'refund') {
                    $q->orWhere(fn ($q) => $q->where('r.status', '<>', 'succeeded')->whereNull('r.economics_posted_at'));
                }
                if ($type === 'commission_refund') {
                    $q->orWhere('a.type', '<>', 'refund')->orWhere('a.commission_amount_delta', '>=', 0);
                }
            });
            $checks['attribution_'.$type] = ! (clone $q)->exists();
            foreach ($q->limit(30)->get(['l.source_key']) as $row) {
                $findings[] = ['severity' => 'error', 'check' => 'attribution_'.$type, 'reference' => $row->source_key, 'message' => 'Amount, currency, owner or recognition state differs from the source.'];
            }
        }
        $summary = $this->finance->summary($vendor, $currency);
        $checks['ledger_sum'] = $summary['ledger_balance'] === (int) (clone $ledger)->sum('l.amount');
        if ($summary['ledger_balance'] < 0) {
            $findings[] = ['severity' => 'warning', 'check' => 'negative_balance', 'reference' => '', 'message' => 'Refunds or other posted deductions exceed earnings. This is a liability, not automatically corruption.'];
        }
        if ($summary['reserved'] > max(0,$summary['available_ledger'])) {
            $findings[] = ['severity' => 'warning', 'check' => 'reservation', 'reference' => '', 'message' => 'Reserved payouts exceed available ledger funds. Review before payment.'];
        }

        return ['status' => in_array(false,$checks,true) ? 'error' : ($findings ? 'warning' : 'healthy'), 'checks' => $checks, 'findings' => $findings, 'summary' => $summary];
    }
}
