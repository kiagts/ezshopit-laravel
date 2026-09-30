<?php

namespace App\Services;

use App\Enums\{GiftCardStatus, GiftCardTransactionType};
use App\Models\{GiftCard, GiftCardTransaction, Order};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardService
{
    public function normalize(string $code): string { return preg_replace('/[^A-Z0-9]/', '', strtoupper($code)); }
    public function hash(string $code): string { return hash('sha256', $this->normalize($code)); }
    public function generateCode(): string { return 'GIFT-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)); }
    public function find(string $code): ?GiftCard { return GiftCard::where('code_hash', $this->hash($code))->first(); }
    public function balance(GiftCard $card): int { return $card->balance(); }

    public function issue(int $amount, string $currency, ?int $actor = null, array $attrs = []): array
    {
        if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Gift card amount must be positive.']);
        $code = $this->generateCode();
        $card = GiftCard::create(array_merge(['code_hash' => $this->hash($code), 'code_last_four' => substr($this->normalize($code), -4), 'currency_code' => strtoupper($currency), 'initial_amount' => $amount, 'status' => GiftCardStatus::Active, 'issued_at' => now(), 'issued_by' => $actor], $attrs));
        $card->transactions()->create(['type' => GiftCardTransactionType::Issue, 'amount' => $amount, 'currency_code' => $card->currency_code, 'actor_id' => $actor, 'description' => 'Gift card issued', 'idempotency_key' => 'issue:'.$card->id]);
        DB::afterCommit(fn()=>event(new \App\Events\FinancialNotificationRequested("gift_card",$card->id,"issued")));
        return [$card, $code];
    }

    public function redeem(Order $order, string $code, int $requested): int
    {
        return DB::transaction(function () use ($order, $code, $requested) {
            $card = GiftCard::where('code_hash', $this->hash($code))->lockForUpdate()->first();
            if (!$card) throw ValidationException::withMessages(['gift_card' => 'Gift card is invalid, expired, unavailable, or in the wrong currency.']);
            $key = 'order:'.$order->id.':gift-card:'.$card->id;
            $existing = $card->transactions()->where('idempotency_key', $key)->first();
            if ($existing) return $existing->amount;
            if (!$card->usable() || $card->currency_code !== $order->currency) throw ValidationException::withMessages(['gift_card' => 'Gift card is invalid, expired, unavailable, or in the wrong currency.']);
            $amount = min($card->balance(), $requested);
            if ($amount <= 0) throw ValidationException::withMessages(['gift_card' => 'Gift card has no remaining balance.']);
            $card->transactions()->create(['type' => GiftCardTransactionType::Redeem, 'amount' => $amount, 'currency_code' => $card->currency_code, 'reference_type' => 'order', 'reference_id' => $order->id, 'idempotency_key' => $key, 'description' => 'Order gift-card redemption']);
            return $amount;
        }, 3);
    }

    public function refundToGiftCard(Order $order, GiftCard $card, int $amount, string $key, ?int $actor = null, array $metadata = []): GiftCardTransaction
    {
        return DB::transaction(function () use ($order, $card, $amount, $key, $actor, $metadata) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $card = GiftCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ($card->currency_code !== $order->currency || $amount <= 0) throw ValidationException::withMessages(['gift_card' => 'Gift card refund is invalid.']);
            $existing = $card->transactions()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->amount !== $amount || $existing->reference_id !== $order->id || $existing->type !== GiftCardTransactionType::Refund) throw ValidationException::withMessages(['gift_card'=>'Refund key conflict.']);
                return $existing;
            }
            $prior = (int) $card->transactions()->where('reference_type', 'order')->where('reference_id', $order->id)->where('type', GiftCardTransactionType::Refund)->sum('amount');
            $funded = (int) $card->transactions()->where('reference_type','order')->where('reference_id',$order->id)->where('type',GiftCardTransactionType::Redeem)->lockForUpdate()->get()->sum('amount');
            if ($prior + $amount > min($funded, (int) $order->gift_card_applied_total)) throw ValidationException::withMessages(['gift_card' => 'Refund exceeds gift-card funding.']);
            return $card->transactions()->create(['type' => GiftCardTransactionType::Refund, 'amount' => $amount, 'currency_code' => $card->currency_code, 'reference_type' => 'order', 'reference_id' => $order->id, 'idempotency_key' => $key, 'actor_id' => $actor, 'description' => 'Refund to original gift card', 'metadata'=>$metadata]);
        }, 3);
    }

    public function disable(GiftCard $card): void { $card->update(['status' => GiftCardStatus::Disabled]); }
}

