<?php

namespace App\Services\Disputes;

use App\Enums\DisputeReason;
use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Enums\ReturnStatus;
use App\Events\DisputeActivity;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Services\Refunds\RefundService;
use App\Services\Returns\ReturnWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisputeWorkflowService
{
    public function __construct(private DisputeEligibilityService $eligibility, private DisputeEvidenceService $evidence) {}

    public function open(Order $order, User $actor, array $input, array $files = []): Dispute
    {
        // Preserve the old delivery reason as a documented input alias.
        if (($input['reason'] ?? null) === 'delivery') {
            $input['reason'] = 'item_not_received';
        }
        $data = Validator::make($input, ['reason' => ['required', Rule::enum(DisputeReason::class)], 'description' => 'required|string|min:5|max:5000', 'vendor_order_id' => 'nullable|integer', 'return_request_id' => 'nullable|integer', 'refund_id' => 'nullable|integer'])->validate();
        $this->evidence->validate($files);
        $stored = [];
        try {
            return DB::transaction(function () use ($order, $actor, $data, $files, &$stored) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                [$vo,$return,$refund] = $this->eligibility->context($order, $actor, $data);
                $key = hash('sha256', json_encode([$order->id, $vo->id, $data['reason'], $return?->id, $refund?->id]));
                if (Dispute::where('active_key', $key)->exists()) {
                    $this->invalid('An active dispute already covers this issue. Continue that conversation.');
                }
                $dispute = Dispute::create(['dispute_number' => 'DSP-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)),
                    'order_id' => $order->id, 'vendor_order_id' => $vo->id, 'return_request_id' => $return?->id, 'refund_id' => $refund?->id,
                    'opened_by' => $actor->id, 'reason' => $data['reason'], 'description' => $data['description'], 'status' => DisputeStatus::Open, 'active_key' => $key, 'last_activity_at' => now()]);
                $message = $dispute->messages()->create(['author_id' => $actor->id, 'author_role' => $actor->role->value, 'body' => $data['description']]);
                $this->evidence->store($message, $actor, $files, $stored);
                $this->history($dispute, $actor, null, 'Dispute opened.', 'opened');

                return $dispute;
            });
        } catch (\Throwable $e) {
            $this->evidence->cleanup($stored);
            throw $e;
        }
    }

    public function message(Dispute $dispute, User $actor, string $body, array $files = []): void
    {
        Validator::make(['body' => $body], ['body' => 'required|string|min:1|max:5000'])->validate();
        $this->evidence->validate($files);
        $stored = [];
        try {
            DB::transaction(function () use ($dispute, $actor, $body, $files, &$stored) {
                $d = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('message', $d);
                $message = $d->messages()->create(['author_id' => $actor->id, 'author_role' => $actor->role->value, 'body' => $body]);
                $this->evidence->store($message, $actor, $files, $stored);
                $from = $d->status;
                if (($from === DisputeStatus::AwaitingCustomer && $actor->isCustomer()) || ($from === DisputeStatus::AwaitingVendor && $actor->role->value === 'vendor')) {
                    $d->status = DisputeStatus::UnderReview;
                }
                $d->last_activity_at = now();
                $d->save();
                if ($from !== $d->status) {
                    $this->history($d, $actor, $from, 'Requested participant responded.', 'status_changed');
                }
                DB::afterCommit(fn () => event(new DisputeActivity($d->id, 'message_added', $actor->id, $message->id, $d->status->value)));
            });
        } catch (\Throwable $e) {
            $this->evidence->cleanup($stored);
            throw $e;
        }
    }

    public function transition(Dispute $dispute, User $actor, DisputeStatus $target, ?string $note = null): Dispute
    {
        Validator::make(['note' => $note], ['note' => 'nullable|string|max:2000'])->validate();

        return DB::transaction(function () use ($dispute, $actor, $target, $note) {
            $d = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $d);
            if ($d->status === $target) {
                return $d;
            }
            if (! in_array($target, $d->status->targets(), true)) {
                $this->invalid('Invalid dispute transition.');
            }
            if (in_array($target, [DisputeStatus::AwaitingCustomer, DisputeStatus::AwaitingVendor], true) && strlen(trim($note ?? '')) < 5) {
                $this->invalid('Explain what response is needed.');
            }
            if ($target === DisputeStatus::AwaitingVendor && ! $d->vendor_order_id) {
                $this->invalid('Legacy unscoped dispute cannot request a seller response.');
            }
            $from = $d->status;
            $d->update(['status' => $target, 'last_activity_at' => now()]);
            $this->history($d, $actor, $from, $note, $target === DisputeStatus::Closed ? 'closed' : 'status_changed');

            return $d;
        }, 3);
    }

    public function assign(Dispute $dispute, User $actor, ?int $adminId): void
    {
        DB::transaction(function () use ($dispute, $actor, $adminId) {
            $d = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $d);
            if (! $d->status->active()) {
                $this->invalid('Finalized disputes are read-only.');
            }
            if ($adminId && ! User::whereKey($adminId)->where('role', 'admin')->exists()) {
                $this->invalid('Assignment requires an admin.');
            }
            if ($d->assigned_admin_id === $adminId) {
                return;
            }
            $d->update(['assigned_admin_id' => $adminId, 'last_activity_at' => now()]);
            $this->history($d, $actor, $d->status, $adminId ? 'Assigned to admin #'.$adminId : 'Assignment cleared.', 'assigned');
        });
    }

    public function resolve(Dispute $dispute, User $actor, array $input): Dispute
    {
        $data = Validator::make($input, [
            'resolution' => ['required', Rule::enum(DisputeResolution::class)], 'note' => 'required|string|min:5|max:2000',
            'items' => 'sometimes|array|max:30', 'items.*.id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:100000',
            'shipping_amount' => 'sometimes|integer|min:0|max:1000000000000', 'adjustment' => 'sometimes|integer|min:0|max:1000000000000',
        ])->validate();
        $hash = hash('sha256', json_encode($data));

        return DB::transaction(function () use ($dispute, $actor, $data, $hash) {
            Order::whereKey($dispute->order_id)->lockForUpdate()->firstOrFail();
            $d = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $d);
            if ($d->resolution_hash) {
                if ($d->resolution_hash !== $hash) {
                    $this->invalid('A different decision is already recorded.');
                }

return $d;
            }
            if ($d->status !== DisputeStatus::UnderReview) {
                $this->invalid('Start review before resolving a dispute.');
            }
            $outcome = DisputeResolution::from($data['resolution']);
            $refund = null;
            if (! $outcome->financial() && (! empty($data['items']) || ($data['shipping_amount'] ?? 0) > 0 || ($data['adjustment'] ?? 0) > 0)) {
                $this->invalid('Non-financial decisions cannot contain refund instructions.');
            }
            if ($outcome->financial()) {
                if (! $d->vendor_order_id) {
                    $this->invalid('Financial decisions require a seller-scoped dispute.');
                }
                $items = $data['items'] ?? [];
                foreach ($items as $row) {
                    if (! $d->vendorOrder->items()->whereKey($row['id'])->exists()) {
                        $this->invalid('Refund item belongs to another seller.');
                    }
                }
                $shipping = ($data['shipping_amount'] ?? 0) > 0 ? [['vendor_order_id' => $d->vendor_order_id, 'amount' => $data['shipping_amount']]] : [];
                $refund = app(RefundService::class)->create($d->order, $actor, [
                    'key' => 'dispute-resolution:'.$d->id, 'reason' => 'Decision on dispute '.$d->dispute_number,
                    'items' => $items, 'shipping' => $shipping, 'adjustment' => $data['adjustment'] ?? 0,
                ], $d->returnRequest);
                app(RefundService::class)->process($refund, $actor);
            } elseif (in_array($outcome, [DisputeResolution::ReturnApproved, DisputeResolution::ReturnRejected], true)) {
                if (! $d->returnRequest) {
                    $this->invalid('This decision requires a linked return.');
                }
                app(ReturnWorkflowService::class)->transition($d->returnRequest, $actor, $outcome === DisputeResolution::ReturnApproved ? ReturnStatus::Approved : ReturnStatus::Rejected, $data['note']);
            }
            $d->update(['status' => DisputeStatus::Resolved, 'resolution' => $outcome->value, 'resolution_note' => $data['note'],
                'resolved_by' => $actor->id, 'resolved_at' => now(), 'last_activity_at' => now(), 'resolution_hash' => $hash,
                'resolution_refund_id' => $refund?->id, 'active_key' => null]);
            $this->history($d, $actor, DisputeStatus::UnderReview, $data['note'], 'resolved');

            return $d;
        }, 3);
    }

    private function history(Dispute $d, User $actor, ?DisputeStatus $from, ?string $note, string $event): void
    {
        $history = $d->history()->create(['from_status' => $from?->value, 'to_status' => $d->status->value, 'actor_id' => $actor->id, 'actor_role' => $actor->role->value, 'note' => $note, 'resolution' => $d->resolution]);
        DB::afterCommit(fn () => event(new DisputeActivity($d->id,$event,$actor->id,$history->id,$d->status->value)));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['dispute' => $message]);
    }
}

