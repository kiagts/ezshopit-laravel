<?php

namespace App\Http\Controllers\Disputes;

use App\Enums\DisputeReason;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Disputes\DisputeWorkflowService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    private function context(Request $r): array
    {
        $prefix = $r->user()->isCustomer() ? 'account' : $r->user()->role->value;

        return ['prefix' => $prefix, 'layout' => $prefix === 'account' ? 'account.layout' : 'layouts.'.$prefix, 'section' => $prefix === 'account' ? 'account_content' : 'content'];
    }

    public function index(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::enum(DisputeStatus::class)], 'reason' => ['nullable', Rule::enum(DisputeReason::class)], 'date' => 'nullable|date_format:Y-m-d', 'vendor' => 'nullable|integer', 'customer' => 'nullable|integer', 'assigned_admin' => 'nullable|integer']);
        $q = Dispute::with(['order:id,order_number', 'vendorOrder.vendor:id,display_name']);
        if ($r->user()->isCustomer()) {
            $q->where('opened_by', $r->user()->id);
        } elseif (! $r->user()->isAdmin()) {
            $q->whereHas('vendorOrder.vendor', fn ($q) => $q->where('user_id', $r->user()->id)->where('status', 'approved'));
        }
        foreach (['status', 'reason'] as $field) {
            $q->when($f[$field] ?? null, fn ($q, $v) => $q->where($field, $v));
        }
        $q->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('dispute_number', 'like', "%$v%")->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', "%$v%"))))
            ->when($f['date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', $v));
        if ($r->user()->isAdmin()) {
            $q->when($f['customer'] ?? null, fn ($q, $v) => $q->where('opened_by', $v))->when($f['assigned_admin'] ?? null, fn ($q, $v) => $q->where('assigned_admin_id', $v))->when($f['vendor'] ?? null, fn ($q, $v) => $q->whereHas('vendorOrder', fn ($q) => $q->where('vendor_id', $v)));
        }

        return view('disputes.index', $this->context($r) + ['disputes' => $q->latest('last_activity_at')->latest('id')->paginate(15)->withQueryString()]);
    }

    public function create(Request $r, Order $order)
    {
        abort_unless($r->user()->isCustomer() && $order->user_id === $r->user()->id, 403);
        $order->load('vendorOrders.vendor');

        return view('disputes.create', $this->context($r) + ['order' => $order, 'returns' => ReturnRequest::where('order_id', $order->id)->get(), 'refunds' => Refund::where('order_id', $order->id)->get()]);
    }

    public function store(Request $r, Order $order, DisputeWorkflowService $workflow)
    {
        $d = $workflow->open($order, $r->user(), $r->all(), $this->files($r));

        return redirect()->route('account.disputes.show', $d)->with('status', 'Dispute opened.');
    }

    public function show(Request $r, Dispute $dispute)
    {
        Gate::authorize('view', $dispute);
        $dispute->load(['order:id,order_number,currency', 'vendorOrder.vendor:id,display_name,user_id,status', 'vendorOrder.items', 'returnRequest', 'refund.allocations', 'resolutionRefund.allocations', 'history.actor:id,name', 'assignedAdmin:id,name']);
        $messages = $dispute->messages()->with(['author:id,name', 'evidence'])->reorder('id', 'desc')->paginate(20, ['*'], 'messages_page');
        $targets = $r->user()->isAdmin() ? $dispute->status->targets() : [];
        $admins = $r->user()->isAdmin() ? User::where('role', 'admin')->get(['id', 'name']) : collect();

        return view('disputes.show', $this->context($r) + compact('dispute', 'targets', 'admins', 'messages'));
    }

    public function message(Request $r, Dispute $dispute, DisputeWorkflowService $workflow)
    {
        $data = $r->validate(['body' => 'required|string|max:5000']);
        $workflow->message($dispute, $r->user(), $data['body'], $this->files($r));

        return back()->with('status', 'Message added.');
    }

    public function transition(Request $r, Dispute $dispute, DisputeWorkflowService $workflow)
    {
        $data = $r->validate(['target' => ['required', Rule::enum(DisputeStatus::class)], 'note' => 'nullable|string|max:2000']);
        $workflow->transition($dispute, $r->user(), DisputeStatus::from($data['target']), $data['note'] ?? null);

        return back()->with('status', 'Dispute updated.');
    }

    public function assign(Request $r, Dispute $dispute, DisputeWorkflowService $workflow)
    {
        $data = $r->validate(['assigned_admin_id' => 'nullable|integer']);
        $workflow->assign($dispute, $r->user(), isset($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : null);

        return back();
    }

    public function resolve(Request $r, Dispute $dispute, DisputeWorkflowService $workflow)
    {
        $data = $r->all();
        foreach (['shipping_amount', 'adjustment'] as $key) {
            try {
                $data[$key] = Money::parseDecimal($r->input($key)) ?? 0;
            } catch (\Throwable) {
                throw ValidationException::withMessages([$key => 'Enter a valid non-negative amount.']);
            }
        }
        $r->validate(['items' => 'sometimes|array|max:30', 'items.*.id' => 'required|integer', 'items.*.quantity' => 'nullable|integer|min:0|max:100000']);
        $data['items'] = array_values(array_filter($data['items'] ?? [], fn ($row) => ($row['quantity'] ?? 0) > 0));
        try {
            $workflow->resolve($dispute, $r->user(), $data);
        } catch (\RuntimeException $exception) {
            event(new \App\Events\FinancialNotificationRequested("financial_failure",$dispute->id,"dispute-resolution"));
            Log::error('Dispute resolution failed.', ['dispute_id' => $dispute->id, 'exception' => $exception::class]);

            return back()->withErrors(['dispute' => 'Could not finish this resolution. Review the dispute and refund status before retrying.'])->withInput();
        }

        return back()->with('status', 'Decision recorded. Any external refund remains pending provider.');
    }

    public function evidence(Request $r, DisputeEvidence $evidence)
    {
        Gate::authorize('view', $evidence->message->dispute);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->response($evidence->path,null,['Content-Type' => $evidence->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function files(Request $r): array
    {
        $r->validate(['images' => 'sometimes|array|max:5']);

        return $r->file('images',[]);
    }
}

