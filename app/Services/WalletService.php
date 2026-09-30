<?php
namespace App\Services;

use App\Enums\{WalletStatus, WalletTransactionType};
use App\Models\{User, Wallet, WalletTransaction, Order, Currency};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function wallet(User $user, string $currency): Wallet
    {
        $currency = strtoupper($currency);
        if (!$user->isCustomer() || !in_array($currency, $this->currencies(), true)) {
            throw ValidationException::withMessages(['wallet' => 'Choose a customer and a supported currency.']);
        }
        return Wallet::firstOrCreate(['user_id' => $user->id, 'currency_code' => $currency], ['status' => WalletStatus::Active]);
    }

    public function currencies(): array
    {
        return array_values(array_unique([...Currency::where('is_active', true)->pluck('code')->all(), Money::currencyCode()]));
    }

    public function balance(Wallet $wallet): int
    {
        // Current locking reads avoid a stale MySQL repeatable-read snapshot after waiting for the wallet lock.
        $query = $wallet->transactions()->orderBy('id');
        if (DB::transactionLevel() > 0) $query->lockForUpdate();
        return (int) $query->get(['amount', 'type'])->sum(fn ($entry) =>
            $entry->type === WalletTransactionType::Debit ? -$entry->amount : $entry->amount);
    }

    public function credit(Wallet $wallet, int $amount, string $description, ?string $key = null, ?int $actor = null): WalletTransaction
    {
        return $this->post($wallet, WalletTransactionType::Credit, $amount, $description, $key, $actor);
    }

    public function debit(Wallet $wallet, int $amount, string $description, ?string $key = null, ?int $actor = null): WalletTransaction
    {
        return $this->post($wallet, WalletTransactionType::Debit, $amount, $description, $key, $actor);
    }

    public function post(Wallet $wallet, WalletTransactionType $type, int $amount, string $reason, ?string $key = null,
        ?int $actor = null, ?string $referenceType = null, ?int $referenceId = null, array $metadata = []): WalletTransaction
    {
        if ($amount <= 0 || $amount > 1000000000000 || trim($reason) === '' || mb_strlen($reason) > 500 || ($key !== null && (trim($key) === '' || strlen($key) > 191))) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount and a reason.']);
        }
        return DB::transaction(function () use ($wallet, $type, $amount, $reason, $key, $actor, $referenceType, $referenceId, $metadata) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($key && ($existing = WalletTransaction::where('idempotency_key', $key)->lockForUpdate()->first())) {
                if ($existing->wallet_id !== $locked->id || $existing->amount !== $amount || $existing->type !== $type
                    || $existing->reference_type !== $referenceType || $existing->reference_id !== $referenceId) {
                    throw ValidationException::withMessages(['wallet' => 'This transaction key was already used for a different operation.']);
                }
                return $existing;
            }
            if ($locked->status === WalletStatus::Closed && $type !== WalletTransactionType::Reversal) {
                throw ValidationException::withMessages(['wallet' => 'This wallet is closed.']);
            }
            if ($type === WalletTransactionType::Debit && ($locked->status !== WalletStatus::Active || $this->balance($locked) < $amount)) {
                throw ValidationException::withMessages(['wallet' => 'Wallet unavailable or insufficient balance.']);
            }
            $entry = $locked->transactions()->create([
                'type' => $type, 'amount' => $amount, 'currency_code' => $locked->currency_code,
                'description' => $reason, 'idempotency_key' => $key, 'actor_id' => $actor,
                'reference_type' => $referenceType, 'reference_id' => $referenceId, 'metadata' => $metadata,
            ]);
            DB::afterCommit(fn () => event(new \App\Events\FinancialNotificationRequested('wallet', $entry->id, $entry->type->value)));
            return $entry;
        }, 3);
    }

    public function applyToOrder(Order $order, bool $selected): int
    {
        if (!$selected) return 0;
        if (DB::transactionLevel() === 0) throw new \LogicException('Wallet funding requires the order creation transaction.');
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->wallet_applied_total > 0) return (int) $lockedOrder->wallet_applied_total;
            $wallet = Wallet::where('user_id', $order->user_id)->where('currency_code', $order->currency)->lockForUpdate()->first();
            if (!$wallet || $wallet->status !== WalletStatus::Active) {
                throw ValidationException::withMessages(['wallet' => 'Your wallet is no longer available. Review checkout.']);
            }
            $amount = min($this->balance($wallet), (int) $order->grand_total);
            if ($amount <= 0) throw ValidationException::withMessages(['wallet' => 'Your wallet has no available balance.']);
            $this->post($wallet, WalletTransactionType::Debit, $amount, 'Order payment', "order:{$order->id}:wallet:{$wallet->id}",
                $order->user_id, 'order', $order->id);
            $lockedOrder->forceFill(['wallet_applied_total' => $amount])->save();
            $order->wallet_applied_total = $amount;
            return $amount;
        }, 3);
    }

    public function creditOrderRefund(Order $order, int $amount, string $currency, string $key, string $reason, ?int $actor = null, array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($order, $amount, $currency, $key, $reason, $actor, $metadata) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($currency !== $order->currency || !$order->wallet_applied_total) {
                throw ValidationException::withMessages(['wallet' => 'Refund currency or original wallet funding is invalid.']);
            }
            $wallet = Wallet::where('user_id', $order->user_id)->where('currency_code', $currency)->lockForUpdate()->firstOrFail();
            $existing = WalletTransaction::where('idempotency_key', $key)->lockForUpdate()->first();
            $refunded = (int) $wallet->transactions()->where('reference_type', 'order')->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::Reversal)->lockForUpdate()->get(['amount'])->sum('amount');
            if (!$existing && $refunded + $amount > $order->wallet_applied_total) {
                throw ValidationException::withMessages(['wallet' => 'Refund exceeds the original wallet funding.']);
            }
            return $this->post($wallet, WalletTransactionType::Reversal, $amount, $reason, $key, $actor, 'order', $order->id, $metadata);
        }, 3);
    }

    public function reverseCancelledOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!$order->wallet_applied_total) return;
            $wallet = Wallet::where('user_id', $order->user_id)->where('currency_code', $order->currency)->lockForUpdate()->firstOrFail();
            $refunded = (int) $wallet->transactions()->where('reference_type', 'order')->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::Reversal)->lockForUpdate()->get(['amount'])->sum('amount');
            $remaining = $order->wallet_applied_total - $refunded;
            if ($remaining > 0) $this->creditOrderRefund($order, $remaining, $order->currency,
                "order:{$order->id}:wallet:cancellation", 'Cancelled order wallet reversal');
            if ($order->payment?->method === \App\Enums\PaymentMethod::Wallet) {
                $order->payment->update(['status' => \App\Enums\PaymentStatus::Refunded]);
                $order->update(['payment_status' => \App\Enums\PaymentStatus::Refunded]);
            }
        }, 3);
    }

    public function setStatus(Wallet $wallet, WalletStatus $status): void
    {
        DB::transaction(function () use ($wallet, $status) {
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($wallet->status === WalletStatus::Closed || $status === WalletStatus::Closed) {
                throw ValidationException::withMessages(['wallet' => 'Closed wallets cannot be changed here.']);
            }
            $wallet->update(['status' => $status]);
        });
    }
}

