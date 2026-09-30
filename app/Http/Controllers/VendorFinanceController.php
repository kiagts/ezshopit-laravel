<?php

namespace App\Http\Controllers;

use App\Enums\VendorLedgerEntryType;
use App\Http\Requests\Finance\FinanceRequest;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\VendorFinance\FinanceMoney;
use App\Services\VendorFinance\VendorFinanceService;
use App\Services\VendorFinance\VendorFinancialReconciliationService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class VendorFinanceController extends Controller
{
    public function __construct(private VendorFinanceService $finance, private FinanceMoney $money) {}

    private function context(FinanceRequest $r, ?Vendor $vendor = null, ?string $forcedCurrency = null): array
    {
        $admin = $r->user()->isAdmin();
        $vendor = $vendor?->exists ? $vendor : $r->user()->vendor;
        abort_unless($vendor && ($admin || ($r->user()->isVendor() && (int) $vendor->user_id === (int) $r->user()->id)), 403);
        $currencies = $this->finance->currencies($vendor);
        $filters = $r->validated();
        $currency = $forcedCurrency ?? $filters['currency'] ?? $currencies[0];
        if (! in_array($currency, $currencies, true)) {
            throw ValidationException::withMessages(['currency' => 'Choose a currency with seller activity.']);
        }
        $filters['currency'] = $currency;
        $precision = $this->money->precision($currency);
        $base = $admin ? 'admin.finance.' : 'vendor.finance.';
        $url = fn ($section, $extra = []) => route($base.$section, ($admin ? ['vendor' => $vendor] : []) + $filters + $extra);

        return compact('vendor', 'admin', 'currencies', 'filters', 'currency', 'precision', 'url') + ['money' => fn ($v) => Money::formatWithPrecision((int) $v, $currency, $precision)];
    }

    public function index(FinanceRequest $r, ?Vendor $vendor = null)
    {
        $data = $this->context($r, $vendor);

        return view('finance.dashboard', $data + ['summary' => $this->finance->summary($data['vendor'], $data['currency'])]);
    }

    public function transactions(FinanceRequest $r, ?Vendor $vendor = null)
    {
        return $this->history($r, $vendor, false);
    }

    public function statements(FinanceRequest $r, ?Vendor $vendor = null)
    {
        return $this->history($r, $vendor, true);
    }

    private function history(FinanceRequest $r, ?Vendor $vendor, bool $statement)
    {
        $data = $this->context($r, $vendor);

        return view('finance.transactions', $data + ['isStatement' => $statement, 'statement' => $this->finance->statement($data['vendor'], $data['currency'], $data['filters']), 'entries' => $this->finance->transactions($data['vendor'], $data['currency'], $data['filters'])->paginate(25)->withQueryString(), 'types' => VendorLedgerEntryType::cases()]);
    }

    public function earnings(FinanceRequest $r, ?Vendor $vendor = null)
    {
        $data = $this->context($r, $vendor);

        return view('finance.earnings', $data + ['orders' => $this->finance->earnings($data['vendor'], $data['currency'], $data['filters'])->paginate(20)->withQueryString()]);
    }

    public function order(FinanceRequest $r, VendorOrder $vendorOrder)
    {
        abort_unless($r->user()->isAdmin() || (int) $r->user()->vendor?->id === (int) $vendorOrder->vendor_id, 403);

        $data = $this->context($r, $vendorOrder->vendor, $vendorOrder->order->currency);
        $currency = $vendorOrder->order->currency;
        $precision = $this->money->precision($currency);
        $data['currency'] = $currency;
        $data['precision'] = $precision;
        $data['money'] = fn ($v) => Money::formatWithPrecision((int) $v, $currency, $precision);

        return view('finance.order', $data + ['sellerOrder' => $vendorOrder->load(['items', 'commission', 'discountAllocations']), 'entries' => $vendorOrder->ledgerEntries()->where('currency', $currency)->orderBy('created_at')->orderBy('id')->get()]);
    }

    public function reconcile(FinanceRequest $r, Vendor $vendor, VendorFinancialReconciliationService $reconciliation)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $data = $this->context($r, $vendor);

        return view('finance.reconciliation', $data + ['result' => $reconciliation->inspect($vendor, $data['currency'])]);
    }

    public function export(FinanceRequest $r, ?Vendor $vendor = null)
    {
        $data = $this->context($r, $vendor);
        $query = $this->finance->transactions($data['vendor'], $data['currency'], $data['filters']);
        $statement = $this->finance->statement($data['vendor'], $data['currency'], $data['filters']);

        return response()->streamDownload(function () use ($data, $query, $statement) {
            $stream = fopen('php://output', 'w');
            $write = fn ($row) => fputcsv($stream, $row, ',', '"', '');
            $write(['Vendor', $this->finance->csvText($data['vendor']->display_name), 'Currency', $data['currency'], 'Minor unit', $data['precision']]);
            $write(['From', $data['filters']['date_from'] ?? 'All history', 'Through', $data['filters']['date_to'] ?? 'Present']);
            $write(['Opening minor', $statement['opening'], 'All period activity minor', $statement['period_activity'], 'Closing minor', $statement['closing'], 'Filtered activity minor', $statement['filtered_activity']]);
            $write(['Date', 'Reference', 'Type', 'Description', 'Amount minor', 'Currency', 'Amount formatted', 'Running balance minor', 'Running balance formatted']);
            // Bounded eager-loaded batches; no full-ledger collection or per-row queries.
            foreach ($query->lazy(250) as $entry) {
                $reference = $entry->vendorOrder && $entry->vendorOrder->vendor_id === $data['vendor']->id ? $entry->vendorOrder->vendor_order_number : ($entry->vendorPayout && $entry->vendorPayout->vendor_id === $data['vendor']->id ? $entry->vendorPayout->payout_number : $entry->source_key);
                $write([$entry->created_at->format('Y-m-d H:i:s'), $this->finance->csvText($reference), $entry->type->value, $entry->type->label(), $entry->amount, $data['currency'], ($data['money'])($entry->amount), (int) $entry->running_balance, ($data['money'])($entry->running_balance)]);
            }fclose($stream);
        }, 'seller-'.$data['vendor']->id.'-'.$data['currency'].'-statement.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
