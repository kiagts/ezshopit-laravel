<?php

namespace App\Http\Controllers\Returns;

use App\Enums\ReturnCondition;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnEvidence;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnEligibilityService;
use App\Services\Returns\ReturnEvidenceService;
use App\Services\Returns\ReturnWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    private function context(Request $request): array
    {
        $prefix = $request->user()->role === UserRole::Customer ? 'account' : $request->user()->role->value;

        return ['prefix' => $prefix, 'layout' => $prefix === 'account' ? 'account.layout' : "layouts.{$prefix}", 'section' => $prefix === 'account' ? 'account_content' : 'content'];
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::enum(ReturnStatus::class)],
            'date' => 'nullable|date_format:Y-m-d', 'vendor' => 'nullable|integer|min:1', 'customer' => 'nullable|integer|min:1']);
        $user = $request->user();
        $query = ReturnRequest::with(['order', 'vendorOrder.vendor'])->withCount('items');
        if ($user->role === UserRole::Customer) {
            $query->where('user_id', $user->id);
        }
        if ($user->role === UserRole::Vendor) {
            $query->whereHas('vendorOrder.vendor', fn ($q) => $q->where('user_id', $user->id));
        }
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('return_number', 'like', "%{$v}%")->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$v}%"))))
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', $v));
        if ($user->role === UserRole::Admin) {
            $query->when($filters['vendor'] ?? null, fn ($q, $v) => $q->whereHas('vendorOrder', fn ($q) => $q->where('vendor_id', $v)))
                ->when($filters['customer'] ?? null, fn ($q, $v) => $q->where('user_id', $v));
        }

        return view('returns.index', $this->context($request) + ['returns' => $query->latest('id')->paginate(15)->withQueryString(), 'statuses' => ReturnStatus::cases()]);
    }

    public function show(Request $request, ReturnRequest $return)
    {
        Gate::authorize('view', $return);
        $return->load(['order', 'user', 'vendorOrder.vendor', 'items.orderItem', 'evidence', 'history.actor', 'refunds.allocations', 'refunds.fundingAllocations']);
        $targets = Gate::allows('manage', $return) ? array_filter($return->status->targets(), fn ($s) => $s !== ReturnStatus::Inspected) : [];
        if ($request->user()->role === UserRole::Customer) {
            $targets = Gate::allows('cancel', $return) ? [ReturnStatus::Cancelled] : [];
        }
        if ($request->user()->role === UserRole::Admin && $return->status === ReturnStatus::Rejected) {
            $targets[] = ReturnStatus::Approved;
        }
        if ($return->items->isEmpty()) {
            $targets = array_filter($targets, fn ($target) => $target === ReturnStatus::Cancelled);
        }

        return view('returns.show', $this->context($request) + compact('return', 'targets') + [
            'conditions' => array_filter(ReturnCondition::cases(), fn ($s) => $s !== ReturnCondition::Uninspected),
            // Existing disputes are order-level. They are not shared with vendors without vendor scoping.
            'disputes' => $request->user()->role === UserRole::Admin ? Dispute::where('order_id', $return->order_id)->get() : collect(),
        ]);
    }

    public function create(Request $request, Order $order, ReturnEligibilityService $eligibility)
    {
        Gate::authorize('create', [ReturnRequest::class, $order]);
        $items = OrderItem::whereHas('vendorOrder', fn ($q) => $q->where('order_id', $order->id))->with('vendorOrder.vendor')->get();
        $eligible = $items->map(fn ($item) => ['item' => $item, 'eligibility' => $eligibility->check($item)]);

        return view('returns.create', $this->context($request) + compact('order', 'eligible') + ['reasons' => ReturnReason::cases()]);
    }

    public function store(Request $request, Order $order, ReturnWorkflowService $workflow, ReturnEvidenceService $evidence)
    {
        $request->validate(['images' => 'nullable|array|max:5']);
        $return = DB::transaction(function () use ($request, $order, $workflow, $evidence) {
            $return = $workflow->request($order, $request->user(), $request->all());
            if ($request->hasFile('images')) {
                $evidence->upload($return, $request->user(), $request->file('images'));
            }

            return $return;
        });

        return redirect()->route('account.returns.show', $return)->with('status', 'Return requested. You can now add supporting images.');
    }

    public function transition(Request $request, ReturnRequest $return, ReturnWorkflowService $workflow)
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ReturnStatus::class)], 'note' => 'nullable|string|max:2000']);
        $workflow->transition($return, $request->user(), ReturnStatus::from($data['status']), $data['note'] ?? null);

        return back()->with('status', 'Return status updated.');
    }

    public function inspect(Request $request, ReturnRequest $return, ReturnWorkflowService $workflow)
    {
        $workflow->inspect($return, $request->user(), $request->all());

        return back()->with('status', 'Inspection recorded.');
    }

    public function upload(Request $request, ReturnRequest $return, ReturnEvidenceService $evidence)
    {
        $request->validate(['images' => 'required|array|max:5']);
        $evidence->upload($return, $request->user(), $request->file('images', []));

        return back()->with('status', 'Evidence uploaded.');
    }

    public function evidence(Request $request, ReturnEvidence $evidence)
    {
        Gate::authorize('view', $evidence);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->response($evidence->path, 'evidence', [
            'Content-Type' => $evidence->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
