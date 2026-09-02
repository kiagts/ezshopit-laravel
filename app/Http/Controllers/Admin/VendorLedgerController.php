<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorLedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'type' => ['nullable', Rule::enum(VendorLedgerEntryType::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        return view('admin.vendor-ledger.index', [
            'entries' => VendorLedgerEntry::query()
                ->with(['vendor:id,display_name,slug', 'vendorOrder:id,vendor_order_number', 'order:id,order_number', 'refund:id,refund_number'])
                ->when($validated['vendor_id'] ?? null, fn ($query, $vendorId) => $query->where('vendor_id', $vendorId))
                ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->when($validated['reference'] ?? null, function ($query, $reference): void {
                    $query->where(function ($query) use ($reference): void {
                        $query
                            ->where('source_key', 'like', "%{$reference}%")
                            ->orWhereHas('vendorOrder', fn ($query) => $query->where('vendor_order_number', 'like', "%{$reference}%"))
                            ->orWhereHas('order', fn ($query) => $query->where('order_number', 'like', "%{$reference}%"))
                            ->orWhereHas('refund', fn ($query) => $query->where('refund_number', 'like', "%{$reference}%"));
                    });
                })
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'vendors' => Vendor::query()->orderBy('display_name')->get(['id', 'display_name']),
            'types' => VendorLedgerEntryType::cases(),
            'filters' => [
                'vendor_id' => $validated['vendor_id'] ?? '',
                'type' => $validated['type'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
                'reference' => $validated['reference'] ?? '',
            ],
        ]);
    }
}
