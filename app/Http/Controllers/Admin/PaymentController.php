<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:80'],
        ]);

        $search = $validated['q'] ?? null;

        return view('admin.payments.index', [
            'payments' => Payment::query()
                ->with(['order.user'])
                ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('provider_reference', 'like', "%{$search}%")
                        ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
                }))
                ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function show(Payment $payment, RefundFinancials $refundFinancials): View
    {
        $payment->load([
            'order.user',
            'attempts',
            'webhookEvents',
            'refunds.allocations.vendorOrder.vendor',
            'refunds.allocations.orderItem',
        ]);

        return view('admin.payments.show', [
            'payment' => $payment,
            'financialSummary' => $refundFinancials->summaryForPayment($payment),
        ]);
    }
}
