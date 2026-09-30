<?php

namespace App\Services\Notifications;

use App\Enums\RefundStatus;
use App\Events\DisputeActivity;
use App\Events\FinancialNotificationRequested;
use App\Events\ReturnStatusChanged;
use App\Models\Dispute;
use App\Models\GiftCard;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;

class LifecycleNotificationListener
{
    public function __construct(private SendUniqueNotification $sender, private AdminRecipientResolver $admins) {}

    private function send(?User $user, string $type, string $title, string $message, string $url, string $key, string $severity = 'info'): void
    {
        if (! $user) {
            return;
        }
        $class = 'App\\Notifications\\Lifecycle\\'.str_replace(' ', '', ucwords(str_replace('_', ' ', $type))).'Notification';
        $this->sender->handle($user, new $class($title, $message, $url, $key.':user:'.$user->id, $severity, true));
    }

    public function returning(ReturnStatusChanged $e): void
    {
        $r = ReturnRequest::with(['user', 'vendorOrder.vendor.user'])->find($e->returnId);
        if (! $r) {
            return;
        }
        $type = 'return_'.$e->to;
        if (! in_array($e->to, ['requested', 'approved', 'rejected', 'received', 'inspected', 'cancelled', 'refund_pending'], true)) {
            return;
        }
        $key = 'return:'.$r->id.':'.$e->to.':'.($e->version ?? $r->history()->max('id'));
        $message = 'Return '.$r->return_number.' is '.str_replace('_', ' ', $e->to).'. View your return for next steps.';
        if ($e->to === 'rejected' && $e->version) {
            $reason = $r->history()->whereKey($e->version)->value('note');
            if ($reason) $message .= ' '.$reason;
        }
        $this->send($r->user, $type, 'Return update', $message, route('account.returns.show', $r, false), $key);
        if (in_array($e->to, ['requested', 'cancelled'], true)) {
            $this->send($r->vendorOrder->vendor->user, $type, 'Seller return update', $message, route('vendor.returns.show', $r, false), $key);
        }
        if ($e->to === 'refund_pending') {
            foreach ($this->admins->recipients() as $admin) {
                $this->send($admin, $type, 'Return requires refund review', $message, route('admin.returns.show', $r, false), $key, 'action_required');
            }
        }
    }

    public function dispute(DisputeActivity $e): void
    {
        $d = Dispute::with(['opener', 'vendorOrder.vendor.user', 'assignedAdmin'])->find($e->disputeId);
        if (! $d) {
            return;
        }
        $version = $e->version ?? ($e->type === 'message_added' ? $d->messages()->max('id') : $d->history()->max('id'));
        $type = match ($e->type) {
            'opened' => 'dispute_opened','message_added' => 'dispute_message','resolved' => 'dispute_resolved','closed' => 'dispute_closed','status_changed' => 'dispute_response_required',default => null
        };
        if (! $type) {
            return;
        }
        if ($e->type === 'status_changed' && ! in_array(($e->state ?? $d->status->value), ['awaiting_customer', 'awaiting_vendor'], true)) {
            return;
        }
        $users = collect([$d->opener, $d->vendorOrder?->vendor?->user]);
        if (in_array($e->type, ['opened', 'message_added'], true)) {
            $users = $users->merge($d->assignedAdmin ? collect([$d->assignedAdmin]) : $this->admins->recipients());
        }
        foreach ($users->filter()->unique('id') as $u) {
            if ($e->type === 'message_added' && $u->id === $e->actorId) {
                continue;
            }
            if ($e->type === 'status_changed' && ((($e->state ?? $d->status->value) === 'awaiting_customer' && ! $u->isCustomer()) || (($e->state ?? $d->status->value) === 'awaiting_vendor' && ! $u->isVendor()))) {
                continue;
            }
            $prefix = $u->isCustomer() ? 'account' : $u->role->value;
            $message = 'Dispute '.$d->dispute_number.': '.match ($type) {
                'dispute_message' => 'A participant added a response.','dispute_response_required' => 'Your response is required.','dispute_resolved' => 'A marketplace decision is available. Any related external refund may still be pending provider.',default => str_replace('dispute_', '', $type).'.'
            };
            $this->send($u, $type, 'Dispute update', $message, route($prefix.'.disputes.show', $d, false), 'dispute:'.$d->id.':'.$e->type.':'.$version, $type === 'dispute_response_required' ? 'action_required' : 'info');
        }
    }

    public function financial(FinancialNotificationRequested $e): void
    {
        if ($e->entity === 'financial_failure') {
            foreach ($this->admins->recipients() as $a) {
                $this->send($a, 'system_financial_failure', 'Financial operation requires review', 'An attempted financial operation failed. Review the record before retrying.', route('admin.refunds.index', absolute: false), 'financial-failure:'.$e->id.':'.$e->state, 'action_required');
            }
        } elseif ($e->entity === 'refund') {
            $r = Refund::with(['order.user', 'allocations.vendorOrder.vendor.user'])->find($e->id);
            if (! $r) {
                return;
            }
            if ($e->state === 'succeeded') {
                app(CommerceNotificationListener::class)->refundChanged($r, RefundStatus::Succeeded);

                return;
            }
            $pending = $e->state === 'partially_processed';
            $type = $pending ? 'refund_external_pending' : 'refund_processing';
            $message = 'Refund '.$r->refund_number.' for '.Money::format($r->amount, $r->currency).($pending ? ' is approved; external funds await the original payment provider.' : ' is being prepared.');
            $this->send($r->order->user, $type, $pending ? 'Refund awaiting provider' : 'Refund processing', $message, route('account.orders.show', $r->order, false), 'refund:'.$r->id.':'.$e->state);
            if ($pending) {
                foreach ($this->admins->recipients() as $a) {
                    $this->send($a, $type, 'Refund requires provider processing', $message, route('admin.refunds.show', $r, false), 'refund:'.$r->id.':'.$e->state, 'action_required');
                }$this->impact($r);
            }
        } elseif ($e->entity === 'wallet') {
            $t = WalletTransaction::with('wallet.user')->find($e->id);
            if (! $t || $t->reference_type || ! User::find($t->actor_id)?->isAdmin() || ! in_array($t->type->value, ['credit', 'debit'], true)) {
                return;
            }
            $this->send($t->wallet->user, 'wallet_'.$t->type->value, 'Wallet adjustment', 'An administrator recorded a wallet '.$t->type->value.' of '.Money::format($t->amount, $t->currency_code).'. Reference '.$t->id.'.', route('account.wallet', absolute: false), 'wallet-entry:'.$t->id);
        } elseif ($e->entity === 'gift_card') {
            $c = GiftCard::with('recipient')->find($e->id);
            if (! $c?->recipient) {
                return;
            }
            $this->send($c->recipient, 'gift_card_issued', 'Gift card associated', 'A gift card for '.Money::format($c->initial_amount, $c->currency_code).' is associated with your account.', route('account.gift-cards.index', absolute: false), 'gift-card:'.$c->id.':recipient:'.$c->recipient_user_id);
        }
    }

    public function impact(Refund $r): void
    {
        foreach ($r->allocations->pluck('vendorOrder')->filter()->unique('id') as $vo) {
            $this->send($vo->vendor->user,'refund_impact','Seller refund adjustment','Refund '.$r->refund_number.' affects seller order '.$vo->vendor_order_number.'. Review the seller adjustment.',route('vendor.orders.show',$vo,false),'refund:'.$r->id.':impact:'.$vo->id);
        }
    }
}
