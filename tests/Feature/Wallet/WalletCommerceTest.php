<?php
namespace Tests\Feature\Wallet;

use App\Enums\{PaymentMethod, PaymentStatus, WalletStatus, WalletTransactionType};
use App\Models\{User, Vendor, Category, Product, ProductVariant, Inventory, Cart, CartItem, CustomerAddress, ShippingMethod, Order, WalletTransaction};
use App\Services\WalletService;
use App\Services\Checkout\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletCommerceTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(int $credit, bool $selected = true): array
    {
        $user = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $user->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $product = Product::factory()->approved()->create(['vendor_id' => $vendor->id, 'category_id' => Category::factory()->create()->id]);
        $variant = ProductVariant::factory()->default()->create(['product_id' => $product->id, 'base_price' => 10000, 'sale_price' => null]);
        Inventory::updateOrCreate(['product_variant_id' => $variant->id], ['on_hand' => 10, 'reserved' => 0]);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'flat_rate' => 500, 'free_shipping_threshold' => null]);
        $cart = Cart::factory()->customer()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);
        $service = app(WalletService::class);
        $wallet = $service->wallet($user, 'USD');
        if ($credit > 0) $service->credit($wallet, $credit, 'Test funding');
        $this->actingAs($user)->withSession(['checkout.use_wallet' => $selected]);
        $this->get(route('checkout.index'))->assertOk();
        return [$user, $wallet, session(CheckoutSession::IDEMPOTENCY_KEY), $variant];
    }

    public function test_full_funding_is_atomic_paid_and_idempotent(): void
    {
        [$user, $wallet, $key] = $this->checkout(20000);
        $this->assertSame(20000, app(WalletService::class)->balance($wallet));
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame(10500, $order->grand_total);
        $this->assertSame(10500, $order->wallet_applied_total);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(PaymentMethod::Wallet, $order->payment->method);
        $this->assertSame(0, $order->payment->amount);
        $this->assertSame(9500, app(WalletService::class)->balance($wallet));
        $this->assertTrue(app(\App\Services\Payments\CanFulfillVendorOrder::class)->check($order->vendorOrders->first())->allowed);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
        $this->assertSame(1, WalletTransaction::where('type', 'debit')->count());
        $this->get(route('account.orders.show', $order))->assertOk()->assertSeeText('Wallet funded');
    }

    public function test_partial_funding_preserves_order_and_seller_totals(): void
    {
        [, $wallet, $key] = $this->checkout(3000);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame(10500, $order->grand_total);
        $this->assertSame(3000, $order->wallet_applied_total);
        $this->assertSame(7500, $order->payment->amount);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(10500, $order->vendorOrders->first()->grand_total);
        $this->assertSame(0, $order->discount_total);
        $this->assertSame(0, app(WalletService::class)->balance($wallet));
    }

    public function test_unselected_wallet_is_not_spent(): void
    {
        [, $wallet, $key] = $this->checkout(20000, false);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->assertSame(0, Order::firstOrFail()->wallet_applied_total);
        $this->assertSame(20000, app(WalletService::class)->balance($wallet));
    }

    public function test_frozen_wallet_after_preview_rolls_back_order_and_stock(): void
    {
        [, $wallet, $key, $variant] = $this->checkout(20000);
        app(WalletService::class)->setStatus($wallet, WalletStatus::Frozen);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasErrors('wallet');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $variant->inventory()->first()->reserved);
        $this->assertSame(20000, app(WalletService::class)->balance($wallet));
    }

    public function test_balance_is_rechecked_after_preview(): void
    {
        [, $wallet, $key] = $this->checkout(20000);
        app(WalletService::class)->debit($wallet, 18000, 'Other purchase');
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->assertSame(2000, Order::firstOrFail()->wallet_applied_total);
        $this->assertSame(0, app(WalletService::class)->balance($wallet));
    }

    public function test_refund_is_capped_currency_isolated_and_idempotent(): void
    {
        [, $wallet, $key] = $this->checkout(3000);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $service = app(WalletService::class);
        $first = $service->creditOrderRefund($order, 1000, 'USD', 'refund-1', 'Refund');
        $this->assertSame($first->id, $service->creditOrderRefund($order, 1000, 'USD', 'refund-1', 'Refund')->id);
        $this->assertSame(1000, $service->balance($wallet));
        try { $service->creditOrderRefund($order, 1, 'PKR', 'refund-2', 'Wrong currency'); $this->fail(); }
        catch (ValidationException) { $this->assertSame(1000, $service->balance($wallet)); }
        $this->expectException(ValidationException::class);
        $service->creditOrderRefund($order, 2001, 'USD', 'refund-3', 'Too much');
    }

    public function test_ledger_rejects_overdraft_and_is_immutable(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->wallet(User::factory()->customer()->create(), 'USD');
        $entry = $service->credit($wallet, 1000, 'First');
        $service->credit($wallet, 500, 'Second');
        $this->assertSame(1500, $service->balance($wallet));
        try { $service->debit($wallet, 1501, 'Overdraft'); $this->fail(); }
        catch (ValidationException) { $this->assertSame(1500, $service->balance($wallet)); }
        $this->expectException(\LogicException::class);
        $entry->update(['amount' => 1]);
    }

    public function test_admin_credit_history_freeze_and_access_protection(): void
    {
        $customer = User::factory()->customer()->create();
        $this->get(route('admin.wallets.index'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('admin.wallets.index'))->assertForbidden();
        $this->actingAs(User::factory()->vendor()->create())->get(route('admin.wallets.index'))->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.wallets.index'))->assertOk()->assertSeeText($customer->email);
        $data = ['currency' => 'USD', 'type' => 'credit', 'amount' => '25.00', 'reason' => '<script>credit</script>', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('admin.wallets.transact', $customer), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.wallets.transact', $customer), $data)->assertSessionHasNoErrors();
        $entry = WalletTransaction::firstOrFail();
        $this->assertSame(2500, $entry->amount);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(1, WalletTransaction::count());
        $this->get(route('admin.wallets.show', $customer))->assertOk()->assertSee('&lt;script&gt;credit&lt;/script&gt;', false);
        $this->put(route('admin.wallets.status', $entry->wallet_id), ['status' => 'frozen'])->assertSessionHasNoErrors();
        $this->assertSame(WalletStatus::Frozen, $entry->wallet->status);
        $this->actingAs($customer)->get(route('account.wallet'))->assertOk()->assertSeeText('USD 25.00');
        $this->actingAs(User::factory()->customer()->create())->get(route('account.wallet', ['wallet_id' => $entry->wallet_id]))->assertOk()->assertDontSeeText('USD 25.00');
    }

    public function test_full_wallet_works_without_external_payment_methods(): void
    {
        config(['payments.methods' => []]);
        [, , $key] = $this->checkout(20000);
        $this->assertNotEmpty($key);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->assertSame(PaymentMethod::Wallet, Order::firstOrFail()->payment->method);
    }

    public function test_ledger_bulk_mutations_are_blocked(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->wallet(User::factory()->customer()->create(), 'USD');
        $service->credit($wallet, 100, 'Credit');
        try { WalletTransaction::query()->update(['amount' => 1]); $this->fail(); }
        catch (\LogicException) { $this->assertSame(100, $service->balance($wallet)); }
        $this->expectException(\LogicException::class);
        $wallet->transactions()->delete();
    }

    public function test_wallet_cannot_be_deleted_with_history(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->wallet(User::factory()->customer()->create(), 'USD');
        $service->credit($wallet, 100, 'Credit');
        $this->expectException(\Illuminate\Database\QueryException::class);
        $wallet->delete();
    }

    public function test_checkout_cannot_spend_a_different_currency_wallet(): void
    {
        $this->seed(\Database\Seeders\CurrencySeeder::class);
        [, $wallet] = $this->checkout(20000);
        $this->withSession(['currency_code' => 'PKR'])->get(route('checkout.index'))->assertOk()->assertViewHas('walletBalance', 0);
        $this->post(route('checkout.place-order'), ['idempotency_key' => session(CheckoutSession::IDEMPOTENCY_KEY), 'wallet_amount' => 1, 'wallet_id' => $wallet->id])
            ->assertSessionHasErrors('wallet');
        $this->assertSame(0, Order::count());
        $this->assertSame(20000, app(WalletService::class)->balance($wallet));
    }

    public function test_admin_debit_validation_unfreeze_and_ledger_delete_protection(): void
    {
        $user = User::factory()->customer()->create();
        $service = app(WalletService::class);
        $wallet = $service->wallet($user, 'USD');
        $entry = $service->credit($wallet, 1000, 'Initial');
        $data = ['currency' => 'USD', 'type' => 'debit', 'amount' => '4.00', 'reason' => 'Correction', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($user)->post(route('admin.wallets.transact', $user), $data)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.wallets.transact', $user), $data)->assertSessionHasNoErrors();
        $this->assertSame(600, $service->balance($wallet));
        foreach (['-1', 'abc', '9999999999999999999999', '0'] as $amount) {
            $this->post(route('admin.wallets.transact', $user), array_replace($data, ['amount' => $amount, 'idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('amount');
        }
        $this->put(route('admin.wallets.status', $wallet), ['status' => 'frozen'])->assertRedirect();
        $this->put(route('admin.wallets.status', $wallet), ['status' => 'active'])->assertRedirect();
        $service->debit($wallet, 100, 'After unfreeze');
        $this->assertSame(500, $service->balance($wallet));
        $this->expectException(\LogicException::class);
        $entry->delete();
    }

    public function test_failure_after_debit_rolls_back_everything(): void
    {
        [, $wallet, $key, $variant] = $this->checkout(20000);
        $this->mock(\App\Actions\Payments\CreatePaymentForOrder::class)->shouldReceive('handle')->once()
            ->andThrow(ValidationException::withMessages(['payment_method' => 'Unavailable']));
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, WalletTransaction::where('type', 'debit')->count());
        $this->assertSame(20000, app(WalletService::class)->balance($wallet));
        $this->assertSame(0, $variant->inventory()->first()->reserved);
        $this->assertSame(1, CartItem::count());
    }

    public function test_currencies_and_seed_credits_remain_separate_and_repeatable(): void
    {
        $this->seed(\Database\Seeders\CurrencySeeder::class);
        $user = User::factory()->customer()->create(['email' => 'customer@example.com']);
        $this->seed(\Database\Seeders\WalletSeeder::class);
        $this->seed(\Database\Seeders\WalletSeeder::class);
        $service = app(WalletService::class);
        $this->assertSame(25000, $service->balance($service->wallet($user, 'USD')));
        $this->assertSame(1000000, $service->balance($service->wallet($user, 'PKR')));
        $this->assertSame(2, WalletTransaction::count());
        $this->actingAs($user)->get(route('account.wallet', ['currency' => 'PKR']))->assertOk()->assertSeeText('PKR 10,000.00')->assertDontSeeText('USD 250.00');
    }

    public function test_unpaid_card_cancellation_reverses_wallet_once(): void
    {
        [, $wallet, $key] = $this->checkout(3000);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $order->payment->update(['method' => PaymentMethod::Card]);
        $cancel = app(\App\Actions\Orders\CancelOrder::class);
        $cancel->handle($order, \App\Enums\OrderCancellationSource::Customer);
        $cancel->handle($order, \App\Enums\OrderCancellationSource::Customer);
        $this->assertSame(3000, app(WalletService::class)->balance($wallet));
        $this->assertSame(1, WalletTransaction::where('type', 'reversal')->count());
        $this->assertSame(1, WalletTransaction::where('type', 'debit')->count());
    }

    public function test_all_seller_cancellation_reverses_full_wallet_once(): void
    {
        [, $wallet, $key] = $this->checkout(20000);
        $this->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $vendorOrder = Order::firstOrFail()->vendorOrders->first();
        $transition = app(\App\Actions\Orders\Fulfillment\TransitionVendorOrderStatus::class);
        $transition->handle($vendorOrder, \App\Enums\VendorOrderStatus::Cancelled, null, 'Unavailable');
        $transition->handle($vendorOrder, \App\Enums\VendorOrderStatus::Cancelled, null, 'Unavailable');
        $this->assertSame(20000, app(WalletService::class)->balance($wallet));
        $this->assertSame(1, WalletTransaction::where('type', 'reversal')->count());
    }

    public function test_wallet_preserves_tax_promotion_commission_and_ledger(): void
    {
        [, , , $variant] = $this->checkout(20000, false);
        $tax = \App\Models\TaxClass::create(['name' => 'Standard', 'code' => 'standard', 'is_active' => true]);
        $tax->rules()->create(['country_code' => 'PK', 'rate_basis_points' => 1000, 'is_active' => true, 'priority' => 1]);
        $variant->product->update(['tax_class_id' => $tax->id]);
        Cart::firstOrFail()->update(['promotion_id' => \App\Models\Promotion::factory()->fixed(1000)->create()->id]);
        $without = $this->get(route('checkout.index'))->assertOk();
        $this->put(route('checkout.wallet.update'), ['use_wallet' => 1])->assertRedirect();
        $with = $this->get(route('checkout.index'))->assertOk();
        foreach (['subtotal', 'discount_total', 'tax_total', 'shipping_total', 'current_total'] as $field) {
            $this->assertSame($without->viewData($field), $with->viewData($field));
        }
        $this->post(route('checkout.place-order'), ['idempotency_key' => session(CheckoutSession::IDEMPOTENCY_KEY)])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame(900, $order->tax_total);
        $this->assertSame(1000, $order->discount_total);
        $this->assertSame(10400, $order->grand_total);
        \App\Models\CommissionRule::factory()->create(['rate_basis_points' => 1000, 'include_shipping' => false]);
        $vendorOrder = $order->vendorOrders->first();
        $vendorOrder->update(['status' => \App\Enums\VendorOrderStatus::Delivered, 'delivered_at' => now()]);
        $commission = app(\App\Actions\Commissions\RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        $this->assertSame(10000, $commission->original_commissionable_amount);
        $this->assertSame(1000, $commission->original_commission_amount);
        $this->assertDatabaseHas('vendor_ledger_entries', ['vendor_order_id' => $vendorOrder->id, 'amount' => 10500, 'type' => 'sale']);
        $this->assertDatabaseHas('vendor_ledger_entries', ['vendor_order_id' => $vendorOrder->id, 'amount' => -1000, 'type' => 'commission']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.show', $order))->assertOk()->assertSeeText('Wallet funded');
    }
}
