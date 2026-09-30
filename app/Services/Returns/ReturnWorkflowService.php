<?php

namespace App\Services\Returns;

use App\Actions\Inventory\IncreaseInventory;
use App\Enums\ReturnCondition;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Events\ReturnStatusChanged;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnWorkflowService
{
    public function __construct(private ReturnEligibilityService $eligibility) {}

    public function request(Order $order, User $actor, array $data): ReturnRequest
    {
        Gate::forUser($actor)->authorize('create', [ReturnRequest::class, $order]);
        $data = Validator::make($data, [
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:reason,other'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ])->validate();

        return DB::transaction(function () use ($order, $actor, $data) {
            // Serialize reservations of return quantities for this order.
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $selected = collect($data['items'])->keyBy('order_item_id');
            $items = OrderItem::whereIn('id', $selected->keys())->orderBy('id')->lockForUpdate()->get();
            if ($items->count() !== $selected->count()) {
                $this->invalid('Unknown order item.');
            }
            $vendorOrderId = null;
            foreach ($items as $item) {
                if ($item->vendorOrder->order_id !== $order->id) {
                    $this->invalid('Item does not belong to this order.');
                }
                if ($vendorOrderId && $vendorOrderId !== $item->vendor_order_id) {
                    $this->invalid('Submit a separate return for each seller.');
                }
                $vendorOrderId = $item->vendor_order_id;
                $check = $this->eligibility->check($item);
                if ($check['quantity'] < $selected[$item->id]['quantity']) {
                    $this->invalid($check['reason'] ?? 'Quantity exceeds the remaining returnable quantity.');
                }
            }
            $return = ReturnRequest::create([
                'return_number' => 'RET-'.Str::upper(Str::ulid()),
                'order_id' => $order->id, 'vendor_order_id' => $vendorOrderId, 'user_id' => $actor->id,
                'status' => ReturnStatus::Requested, 'reason' => $data['reason'], 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($items as $item) {
                $return->items()->create(['order_item_id' => $item->id, 'quantity' => $selected[$item->id]['quantity'], 'condition' => ReturnCondition::Uninspected]);
            }
            $this->history($return, $actor, null, null);

            return $return;
        }, 3);
    }

    public function transition(ReturnRequest $return, User $actor, ReturnStatus $target, ?string $note = null): ReturnRequest
    {
        return DB::transaction(function () use ($return, $actor, $target, $note) {
            $return = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($actor->role === UserRole::Customer ? 'cancel' : 'manage', $return);
            if (! $return->items()->exists() && $target !== ReturnStatus::Cancelled) {
                $this->invalid('Legacy requests without items cannot enter the operational workflow.');
            }
            if ($actor->role === UserRole::Customer && $target !== ReturnStatus::Cancelled) {
                $this->invalid('Customers may only cancel a requested return.');
            }
            $override = $actor->role === UserRole::Admin && $return->status === ReturnStatus::Rejected && $target === ReturnStatus::Approved;
            if ($target === ReturnStatus::Inspected) {
                $this->invalid('Use item inspection to record this transition.');
            }
            if (! in_array($target, $return->status->targets(), true) && ! $override) {
                $this->invalid('Invalid return status transition.');
            }
            if (($target === ReturnStatus::Rejected || $override) && strlen(trim($note ?? '')) < 5) {
                $this->invalid('Provide a reason of at least five characters.');
            }
            if (strlen($note ?? '') > 2000) {
                $this->invalid('Note is too long.');
            }
            if ($override) {
                Order::whereKey($return->order_id)->lockForUpdate()->firstOrFail();
                foreach ($return->items as $item) {
                    if ($this->eligibility->remaining($item->orderItem) < $item->quantity) {
                        $this->invalid('Quantities have already been claimed by another return.');
                    }
                }
            }
            if ($target === ReturnStatus::Completed && $return->items()->where('resolution', 'refund')->exists()) {
                $this->invalid('A financial refund must be handled separately before completion.');
            }
            if ($target === ReturnStatus::RefundPending && ! $return->items()->where('resolution', 'refund')->exists()) {
                $this->invalid('No inspected item requires refund review.');
            }
            $from = $return->status->value;
            $return->update(['status' => $target]);
            $this->history($return, $actor, $from, $note);

            return $return;
        }, 3);
    }

    public function inspect(ReturnRequest $return, User $actor, array $data): ReturnRequest
    {
        $data = Validator::make($data, [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.condition' => ['required', Rule::enum(ReturnCondition::class), 'not_in:uninspected'],
            'items.*.inspected_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'items.*.resolution' => ['required', 'in:refund,reject,no_action'],
            'items.*.inspection_note' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($return, $actor, $data) {
            $return = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('inspect', $return);
            if ($return->status !== ReturnStatus::Received) {
                $this->invalid('Only received returns may be inspected.');
            }
            $items = $return->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $input = collect($data['items'])->keyBy('id');
            if ($items->keys()->sort()->values()->all() !== $input->keys()->sort()->values()->all()) {
                $this->invalid('Inspect every item in this return exactly once.');
            }
            foreach ($input as $id => $values) {
                $item = $items[$id];
                $qty = (int) $values['inspected_quantity'];
                $condition = ReturnCondition::from($values['condition']);
                if ($qty > $item->quantity || ($condition === ReturnCondition::NotReceived ? $qty !== 0 : $qty < 1)) {
                    $this->invalid('Invalid inspected quantity.');
                }
                $item->fill(collect($values)->except('id')->all());
                if ($condition === ReturnCondition::Restockable && ! $item->restocked_at && $qty > 0) {
                    $variant = $item->orderItem->variant;
                    if (! $variant) {
                        $this->invalid('The original variant is unavailable for restocking.');
                    }
                    app(IncreaseInventory::class)->handle($variant, $qty, $actor,
                        "Return {$return->return_number}; return item {$item->id}; order item {$item->order_item_id}",
                        'return_item', $item->id);
                    $movement = InventoryMovement::where('reference_type', 'return_item')->where('reference_id', $item->id)->firstOrFail();
                    $item->fill(['restock' => true, 'inventory_movement_id' => $movement->id, 'restocked_at' => now()]);
                }
                $item->save();
            }
            $return->update(['status' => ReturnStatus::Inspected]);
            $this->history($return, $actor, ReturnStatus::Received->value, 'Inspection recorded.');

            return $return;
        }, 3);
    }

    public function completeRefund(ReturnRequest $return, User $actor): void
    {
        abort_unless($actor->role === UserRole::Admin, 403);
        DB::transaction(function () use ($return, $actor) {
            $return = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($return->status === ReturnStatus::Completed) return;
            if ($return->status !== ReturnStatus::RefundPending) $this->invalid('Return is not awaiting a refund.');
            $refundableItems = $return->items()->where('resolution','refund')->get();
            if ($refundableItems->isEmpty() || ! $return->refunds()->where('status','succeeded')->exists()) return;
            foreach ($refundableItems as $item) {
                $completed = \App\Models\RefundAllocation::where('return_item_id',$item->id)
                    ->whereHas('refund',fn($q)=>$q->where('status','succeeded'))->sum('quantity');
                if ($completed < $item->inspected_quantity) return;
            }
            if (\App\Models\Refund::where('return_request_id',$return->id)->whereNotIn('status',['succeeded','failed','cancelled'])->exists()) return;
            $return->update(['status'=>ReturnStatus::Completed]);
            $this->history($return,$actor,ReturnStatus::RefundPending->value,'All inspected refund quantities have been financially restored.');
        });
    }

    private function history(ReturnRequest $return, User $actor, ?string $from, ?string $note): void
    {
        $history = $return->history()->create(['from_status' => $from, 'to_status' => $return->status->value,
            'actor_id' => $actor->id, 'actor_role' => $actor->role->value, 'note' => $note]);
        DB::afterCommit(fn () => event(new ReturnStatusChanged($return->id, $from, $return->status->value, $actor->id, $history->id)));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['return' => $message]);
    }
}

