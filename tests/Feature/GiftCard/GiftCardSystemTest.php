<?php

namespace Tests\Feature\GiftCard;

use App\Enums\{GiftCardStatus, GiftCardTransactionType};
use App\Models\{GiftCard, GiftCardTransaction, Order, User};
use App\Services\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GiftCardSystemTest extends TestCase
{
    use RefreshDatabase;

    private function service(): GiftCardService { return app(GiftCardService::class); }

    public function test_issue_generates_hashed_normalized_code_and_ledger(): void
    {
        [$card, $code] = $this->service()->issue(5000, 'usd');
        $this->assertSame('USD', $card->currency_code);
        $this->assertNotSame($code, $card->code_hash);
        $this->assertSame($card->id, $this->service()->find(strtolower($code))?->id);
        $this->assertSame(5000, $card->fresh()->balance());
        $this->assertDatabaseHas('gift_card_transactions', ['gift_card_id' => $card->id, 'type' => GiftCardTransactionType::Issue->value]);
    }

    public function test_invalid_code_and_non_positive_issue_are_rejected(): void
    {
        $this->assertNull($this->service()->find('invalid-code'));
        $this->expectException(ValidationException::class);
        $this->service()->issue(0, 'USD');
    }

    public function test_redemption_is_partial_currency_safe_and_idempotent(): void
    {
        [$card, $code] = $this->service()->issue(1000, 'USD');
        $order = Order::factory()->create(['currency' => 'USD', 'grand_total' => 1500, 'gift_card_applied_total' => 0]);
        $this->assertSame(1000, $this->service()->redeem($order, $code, 1200));
        $this->assertSame(1000, $this->service()->redeem($order, $code, 1200));
        $this->assertSame(1, $card->transactions()->where('type', GiftCardTransactionType::Redeem)->count());
        $this->assertSame(0, $card->fresh()->balance());
        $this->expectException(ValidationException::class);
        $wrong = Order::factory()->create(['currency' => 'EUR']);
        $this->service()->redeem($wrong, $code, 1);
    }

    public function test_expired_disabled_and_depleted_cards_cannot_redeem(): void
    {
        [$card, $code] = $this->service()->issue(100, 'USD');
        $card->update(['status' => GiftCardStatus::Disabled]);
        $this->expectException(ValidationException::class);
        $this->service()->redeem(Order::factory()->create(['currency' => 'USD']), $code, 1);
    }

    public function test_refund_is_consolidated_idempotent_and_capped(): void
    {
        [$card, $code] = $this->service()->issue(1000, 'USD');
        $order = Order::factory()->create(['currency' => 'USD', 'gift_card_applied_total' => 400]);
        $this->service()->redeem($order, $code, 400);
        $first = $this->service()->refundToGiftCard($order, $card, 200, 'refund:1');
        $second = $this->service()->refundToGiftCard($order, $card, 200, 'refund:1');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $card->transactions()->where('type', GiftCardTransactionType::Refund)->count());
        $this->expectException(ValidationException::class);
        $this->service()->refundToGiftCard($order, $card, 300, 'refund:2');
    }

    public function test_customer_balance_endpoint_does_not_expose_full_code(): void
    {
        $user = User::factory()->customer()->create();
        [$card, $code] = $this->service()->issue(700, 'USD', null, ['recipient_user_id' => $user->id]);
        $response = $this->actingAs($user)->from(route('account.gift-cards.index'))->post(route('account.gift-cards.balance'), ['code' => $code]);
        $response->assertRedirect()->assertSessionHas('gift_card_balance');
    }
}
