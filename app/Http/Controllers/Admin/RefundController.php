<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Refunds\CreateRefund;
use App\Actions\Refunds\ProcessRefund;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateRefundRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.collect(RefundStatus::cases())->pluck('value')->implode(',')],
            'provider' => ['nullable', 'string', 'max:80'],
        ]);

        $search = $validated['q'] ?? null;

        return view('admin.refunds.index', [
            'refunds' => Refund::query()
                ->with(['order.user', 'payment'])
                ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('refund_number', 'like', "%{$search}%")
                        ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
                }))
                ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
                ->when(filled($validated['provider'] ?? null), fn (Builder $query) => $query->where('provider', $validated['provider']))
                ->latest('requested_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'provider' => $validated['provider'] ?? '',
            ],
        ]);
    }

    public function store(CreateRefundRequest $request, Order $order, CreateRefund $createRefund, ProcessRefund $processRefund): RedirectResponse
    {
        $refund = $createRefund->handle(
            $order,
            $request->user(),
            $request->validated('reason'),
            $request->validated('notes'),
            $request->validated('allocations'),
        );

        try {
            $processRefund->handle($refund);
            $message = 'Refund request created and sent for provider processing.';
        } catch (ValidationException $exception) {
            $message = $exception->getMessage();
        }

        return redirect()->route('admin.refunds.show', $refund)->with('status', $message);
    }

    public function show(Refund $refund, RefundFinancials $refundFinancials): View
    {
        return view('admin.refunds.show', [
            'refund' => $refund->load([
                'order.user',
                'payment',
                'requestedBy',
                'allocations.vendorOrder.vendor',
                'allocations.orderItem',
            ]),
            'financialSummary' => $refundFinancials->summaryForPayment($refund->payment),
        ]);
    }
}
