<?php

namespace App\Services\Refunds;

use App\Actions\Commissions\ApplyRefundCommissionAdjustment;
use App\Actions\Refunds\CreateRefund;
use App\Actions\VendorLedger\PostVendorCommissionRefundCredit;
use App\Actions\VendorLedger\PostVendorRefundDebit;
use App\Enums\GiftCardTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\RefundFundingSource;
use App\Enums\RefundFundingStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundAllocation;
use App\Models\RefundFundingAllocation;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\GiftCardService;
use App\Services\Returns\ReturnWorkflowService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(private RefundCalculator $calculator) {}

    public function create(Order $order, User $actor, array $data, ?ReturnRequest $return = null): Refund
    {
        abort_unless($actor->role === UserRole::Admin, 403);
        $data = Validator::make($data, [
            'key' => 'required|string|max:100', 'reason' => 'required|string|min:5|max:255', 'notes' => 'nullable|string|max:2000',
            'items' => 'present|array|max:30', 'items.*.id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:100000',
            'shipping' => 'sometimes|array|max:20', 'shipping.*.vendor_order_id' => 'required|integer|distinct', 'shipping.*.amount' => 'required|integer|min:1|max:1000000000000',
            'adjustment' => 'sometimes|integer|min:0|max:1000000000000',
        ])->validate();
        $fingerprint = hash('sha256', json_encode([$order->id, $return?->id, $data]));

        return DB::transaction(function () use ($order, $actor, $data, $return, $fingerprint) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = Refund::where('idempotency_key', $data['key'])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_hash !== $fingerprint) {
                    $this->invalid('Refund key was already used for different instructions.');
                }

                return $existing;
            }
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($payment->currency !== $order->currency || ! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                $this->invalid('Only collected funding in the order currency can be refunded.');
            }
            $returnItems = collect();
            if ($return) {
                $return = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
                if ($return->order_id !== $order->id || $return->status !== ReturnStatus::RefundPending) {
                    $this->invalid('Return must belong to this order and await refund review.');
                }
                if (($data['adjustment'] ?? 0) > 0) {
                    $this->invalid('Return refunds cannot contain unrelated adjustments.');
                }
                $returnItems = $return->items()->where('resolution', 'refund')->get()->keyBy('order_item_id');
            }
            $prepared = [];
            foreach ($data['items'] as $row) {
                $item = OrderItem::whereKey($row['id'])->lockForUpdate()->firstOrFail();
                if ($item->vendorOrder->order_id !== $order->id) {
                    $this->invalid('Item does not belong to this order.');
                }
                $ri = $returnItems[$item->id] ?? null;
                if ($return) {
                    $priorQty = RefundAllocation::where('return_item_id', $ri?->id ?? 0)->whereHas('refund', fn ($q) => $q->whereNotIn('status', ['failed', 'cancelled']))->sum('quantity');
                    if (! $ri || $item->vendor_order_id !== $return->vendor_order_id || $row['quantity'] > $ri->inspected_quantity - $priorQty) {
                        $this->invalid('Refund exceeds inspected return quantity.');
                    }
                }
                $prepared[] = $this->calculator->item($item, (int) $row['quantity'], $ri?->id);
            }
            foreach ($data['shipping'] ?? [] as $row) {
                $vo = $order->vendorOrders()->whereKey($row['vendor_order_id'])->first();
                if (! $vo || ($return && $vo->id !== $return->vendor_order_id)) {
                    $this->invalid('Shipping must belong to the same seller order.');
                }
                $prior = RefundAllocation::where('vendor_order_id', $vo->id)->where('type', 'shipping')->whereHas('refund', fn ($q) => $q->whereNotIn('status', ['failed', 'cancelled']))->sum('amount');
                if ($row['amount'] > $vo->shipping_total - $prior) {
                    $this->invalid('Shipping refund exceeds historical shipping.');
                }
                $prepared[] = ['type' => 'shipping', 'vendor_order_id' => $vo->id, 'amount' => $row['amount'], 'seller_amount' => $row['amount'], 'merchandise_amount' => 0, 'tax_amount' => 0];
            }
            if (($data['adjustment'] ?? 0) > 0) {
                $prepared[] = ['type' => 'adjustment', 'amount' => $data['adjustment'], 'seller_amount' => 0, 'merchandise_amount' => 0, 'tax_amount' => 0];
            }
            $amount = array_sum(array_column($prepared, 'amount'));
            $prior = (int) Refund::where('order_id', $order->id)->whereNotIn('status', ['failed', 'cancelled'])->sum('amount');
            if ($amount <= 0 || $amount + $prior > $order->grand_total) {
                $this->invalid('Refund exceeds remaining order capacity or has no refundable value.');
            }
            $funding = $this->funding($order, $payment, $amount);
            $refund = app(CreateRefund::class)->persistPrepared($order, $payment, $actor, $data['reason'], $prepared, [
                'idempotency_key' => $data['key'], 'request_hash' => $fingerprint, 'return_request_id' => $return?->id,
                'vendor_order_id' => $return?->vendor_order_id,
                'notes' => $data['notes'] ?? null,
            ]);
            foreach ($funding as $source) {
                $refund->fundingAllocations()->create($source + ['currency' => $order->currency, 'status' => RefundFundingStatus::Pending,
                    'idempotency_key' => "refund:{$refund->id}:{$source['source_type']}:{$source['source_id']}"]);
            }

            DB::afterCommit(fn()=>event(new \App\Events\FinancialNotificationRequested("refund",$refund->id,"processing")));
            return $refund;
        }, 3);
    }

    private function funding(Order $order, Payment $payment, int $amount): array
    {
        if ($order->gift_card_applied_total + $order->wallet_applied_total + $payment->amount !== $order->grand_total) {
            $this->invalid('Order funding snapshots do not reconcile.');
        }
        $pending = fn ($type, $id) => (int) RefundFundingAllocation::where('source_type', $type)->where('source_id', $id)
            ->whereHas('refund', fn ($q) => $q->where('order_id', $order->id)->whereNotIn('status', ['failed', 'cancelled']))
            ->where('status', '!=', 'completed')->sum('amount');
        $sources = [];
        $giftDebits = GiftCardTransaction::where('reference_type', 'order')->where('reference_id', $order->id)->where('type', GiftCardTransactionType::Redeem)->lockForUpdate()->get();
        if ((int) $giftDebits->sum('amount') !== (int) $order->gift_card_applied_total) {
            $this->invalid('Original Gift Card funding cannot be verified.');
        }
        foreach ($giftDebits->groupBy('gift_card_id') as $cardId => $debits) {
            $refunded = GiftCardTransaction::where('gift_card_id', $cardId)->where('reference_type', 'order')->where('reference_id', $order->id)->where('type', GiftCardTransactionType::Refund)->lockForUpdate()->get()->sum('amount');
            $sources[] = ['source_type' => 'gift_card', 'source_id' => (int) $cardId, 'capacity' => (int) $debits->sum('amount') - $refunded - $pending('gift_card', $cardId)];
        }
        $walletDebits = WalletTransaction::where('reference_type', 'order')->where('reference_id', $order->id)->where('type', WalletTransactionType::Debit)->lockForUpdate()->get();
        if ((int) $walletDebits->sum('amount') !== (int) $order->wallet_applied_total) {
            $this->invalid('Original wallet funding cannot be verified.');
        }
        foreach ($walletDebits->groupBy('wallet_id') as $walletId => $debits) {
            $refunded = WalletTransaction::where('wallet_id', $walletId)->where('reference_type', 'order')->where('reference_id', $order->id)->where('type', WalletTransactionType::Reversal)->lockForUpdate()->get()->sum('amount');
            $sources[] = ['source_type' => 'wallet', 'source_id' => (int) $walletId, 'capacity' => (int) $debits->sum('amount') - $refunded - $pending('wallet', $walletId)];
        }
        $legacy = (int) Refund::where('order_id', $order->id)->whereNull('idempotency_key')->whereNotIn('status', ['failed', 'cancelled'])->sum('amount');
        $externalUsed = (int) RefundFundingAllocation::where('source_type', 'external_payment')->where('source_id', $payment->id)->whereHas('refund', fn ($q) => $q->whereNotIn('status', ['failed', 'cancelled']))->sum('amount');
        $sources[] = ['source_type' => 'external_payment', 'source_id' => $payment->id, 'capacity' => $payment->amount - $legacy - $externalUsed,
            'payment_attempt_id' => $payment->attempts()->where('status', 'succeeded')->latest('id')->value('id')];
        $out = [];
        foreach ($sources as $source) {
            if ($source['capacity'] < 0) {
                $this->invalid('Funding source refund capacity is inconsistent.');
            }
            $take = min($amount, $source['capacity']);
            if ($take > 0) {
                unset($source['capacity']);
                $out[] = $source + ['amount' => $take];
                $amount -= $take;
            }
        }
        if ($amount > 0) {
            $this->invalid('Refund exceeds original funding still available to restore.');
        }

        return $out;
    }

    public function process(Refund $refund, User $actor): Refund
    {
        try {
            return $this->processLocked($refund, $actor);
        } catch (\RuntimeException $error) {
            \Log::error('Refund orchestration failed.', ['refund_id' => $refund->id, 'exception' => $error::class]);
            if (DB::transactionLevel() === 0) {
                event(new \App\Events\FinancialNotificationRequested('financial_failure', $refund->id, 'refund-processing'));
            }
            throw $error;
        }
    }

    private function processLocked(Refund $refund, User $actor): Refund
    {
        abort_unless($actor->role === UserRole::Admin, 403);

        return DB::transaction(function () use ($refund, $actor) {
            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (! $refund->idempotency_key) {
                $this->invalid('Legacy provider refunds must use their verified provider lifecycle.');
            }
            if (in_array($refund->status, [RefundStatus::Succeeded, RefundStatus::PartiallyProcessed], true)) {
                return $refund;
            }
            if ($refund->status !== RefundStatus::Pending) {
                $this->invalid('Refund cannot be processed in this state.');
            }
            $reservedTotal = (int) Refund::where('order_id', $order->id)->whereNotIn('status', ['failed', 'cancelled'])->sum('amount');
            if ($refund->currency !== $order->currency || $reservedTotal > $order->grand_total) {
                $this->invalid('Refund currency or reserved order capacity is inconsistent.');
            }
            $allocations = $refund->fundingAllocations()->orderBy('id')->lockForUpdate()->get();
            if ((int) $allocations->sum('amount') !== $refund->amount) {
                $this->invalid('Refund funding does not reconcile.');
            }
            foreach ($allocations as $allocation) {
                if ($allocation->currency !== $order->currency) {
                    $this->invalid('Refund currency mismatch.');
                }
                $transaction = null;
                if ($allocation->source_type === RefundFundingSource::GiftCard) {
                    $transaction = app(GiftCardService::class)->refundToGiftCard($order, GiftCard::findOrFail($allocation->source_id), $allocation->amount, $allocation->idempotency_key, $actor->id, ['refund_id' => $refund->id]);
                    $status = RefundFundingStatus::Completed;
                } elseif ($allocation->source_type === RefundFundingSource::Wallet) {
                    $transaction = app(WalletService::class)->creditOrderRefund($order, $allocation->amount, $order->currency, $allocation->idempotency_key, 'Refund '.$refund->refund_number, $actor->id, ['refund_id' => $refund->id]);
                    if ($transaction->wallet_id !== $allocation->source_id) {
                        $this->invalid('Original wallet does not match refund instruction.');
                    }
                    $status = RefundFundingStatus::Completed;
                } else {
                    $status = app(ExternalRefundGateway::class)->request($allocation);
                    if ($status !== RefundFundingStatus::PendingProvider) {
                        $this->invalid('No verified external refund adapter is configured.');
                    }
                }
                $allocation->update(['status' => $status, 'transaction_id' => $transaction?->id]);
            }
            $pending = $allocations->contains(fn ($a) => $a->fresh()->status === RefundFundingStatus::PendingProvider);
            $refund->update(['status' => $pending ? RefundStatus::PartiallyProcessed : RefundStatus::Succeeded,
                'processed_by' => $actor->id, 'processed_at' => $pending ? null : now(), 'economics_posted_at' => now()]);
            foreach ($refund->allocations()->get() as $commerce) {
                app(PostVendorRefundDebit::class)->handle($commerce);
                $adjustment = app(ApplyRefundCommissionAdjustment::class)->handle($commerce);
                // Existing legacy action logs adapter errors; require the ledger post to succeed in this atomic boundary.
                if ($adjustment) {
                    app(PostVendorCommissionRefundCredit::class)->handle($adjustment);
                }
            }
            // External Payment/PaymentAttempt status is untouched without actual provider reversal.
            if (! $pending && $order->payment->amount === 0) {
                $returned = (int) Refund::where('order_id', $order->id)->where('status', 'succeeded')->sum('amount');
                $status = $returned === $order->grand_total ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
                $order->payment->update(['status' => $status]);
                $order->update(['payment_status' => $status]);
            }
            if (! $pending && $refund->return_request_id) {
                app(ReturnWorkflowService::class)->completeRefund($refund->returnRequest, $actor);
            }

            $state=$refund->status->value;
            DB::afterCommit(fn()=>event(new \App\Events\FinancialNotificationRequested("refund",$refund->id,$state)));
            return $refund->fresh();
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}


