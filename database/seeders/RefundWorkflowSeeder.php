<?php

namespace Database\Seeders;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\GiftCardService;
use App\Services\Refunds\RefundCalculator;
use App\Services\Refunds\RefundService;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        if (! $admin) {
            return;
        }
        $this->financialExamples($admin);
        // Use only explicitly marked, genuinely funded demo returns. Never invent
        // balances, payment success, delivery, inspection or sale history.
        $returns = ReturnRequest::where('notes', 'Operational return demo: refund_pending')
            ->where('status', ReturnStatus::RefundPending)->with(['order.payment', 'items'])->get();
        foreach ($returns as $return) {
            if (! $return->order->payment || $return->refunds()->exists()) {
                continue;
            }
            $items = $return->items->where('resolution', 'refund')->map(fn ($item) => [
                'id' => $item->order_item_id, 'quantity' => $item->inspected_quantity,
            ])->values()->all();
            if (! $items) {
                continue;
            }
            try {
                foreach ($items as $item) {
                    app(RefundCalculator::class)->item(OrderItem::findOrFail($item['id']), $item['quantity']);
                }
            } catch (ValidationException $exception) {
                $this->command?->warn('Preserved exhausted demo return '.$return->id.': '.$exception->getMessage());

                continue;
            }
            $service = app(RefundService::class);
            $refund = $service->create($return->order, $admin, [
                'key' => 'demo-return-refund:'.$return->id,
                'reason' => 'Operational demo: inspected return refund', 'items' => $items,
            ], $return);
            $service->process($refund, $admin);
        }
    }

    private function financialExamples(User $admin): void
    {
        // Append named financial demo orders only in an established demo catalog.
        // No historical orders or inventory movements are rewritten.
        if (! Order::where('order_number', 'like', 'DEMO-LIFE-%')->exists()) {
            return;
        }
        $variant = ProductVariant::whereHas('product.vendor', fn ($q) => $q->where('status', 'approved'))->with('product')->orderBy('id')->first();
        $shipping = ShippingMethod::orderBy('id')->first();
        if (! $variant || ! $shipping) {
            return;
        }
        foreach (['pending', 'internal', 'partial', 'split'] as $scenario) {
            DB::transaction(function () use ($scenario, $variant, $shipping, $admin) {
                $number = 'DEMO-REFUND-'.strtoupper($scenario);
                if (Order::where('order_number', $number)->exists()) {
                    return;
                }
                $customer = User::factory()->customer()->create(['name' => 'Refund Demo '.ucfirst($scenario), 'email' => 'refund.'.$scenario.'@example.com']);
                $order = Order::factory()->create([
                    'order_number' => $number, 'user_id' => $customer->id, 'customer_address_id' => null,
                    'currency' => 'USD', 'items_subtotal' => 10000, 'discount_total' => 1000, 'tax_total' => 900, 'shipping_total' => 100, 'grand_total' => 10000,
                    'status' => 'processing', 'payment_status' => 'paid',
                ]);
                $vo = VendorOrder::factory()->create([
                    'order_id' => $order->id, 'vendor_id' => $variant->product->vendor_id, 'shipping_method_id' => $shipping->id,
                    'vendor_order_number' => $number.'-01', 'status' => 'processing', 'items_subtotal' => 10000,
                    'discount_total' => 1000, 'vendor_discount_total' => 1000, 'marketplace_discount_total' => 0, 'tax_total' => 900, 'shipping_total' => 100, 'grand_total' => 10000,
                ]);
                $item = OrderItem::factory()->create([
                    'vendor_order_id' => $vo->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name.' (financial demo)', 'sku' => $variant->sku,
                    'quantity' => 2, 'unit_price' => 5000, 'line_total' => 10000, 'discount_total' => 1000,
                    'vendor_discount_total' => 1000, 'marketplace_discount_total' => 0, 'net_line_total' => 9000, 'tax_total' => 900,
                ]);
                [$card,$code] = app(GiftCardService::class)->issue(3000, 'USD');
                app(GiftCardService::class)->redeem($order, $code, 3000);
                $order->update(['gift_card_applied_total' => 3000, 'gift_card_last_four' => $card->code_last_four]);
                $walletAmount = $scenario === 'split' ? 2000 : 7000;
                $wallet = app(WalletService::class)->wallet($customer, 'USD');
                app(WalletService::class)->credit($wallet, $walletAmount, 'Demo store credit', 'demo-funding:'.$order->id, $admin->id);
                app(WalletService::class)->applyToOrder($order, true);
                // A named demo cash receipt is an original payment snapshot, never an external refund success.
                Payment::factory()->create([
                    'order_id' => $order->id, 'method' => $scenario === 'split' ? 'cash_on_delivery' : 'wallet',
                    'provider' => $scenario === 'split' ? 'cash_on_delivery' : 'wallet', 'currency' => 'USD',
                    'amount' => 10000 - 3000 - $walletAmount, 'status' => 'paid', 'paid_at' => now(),
                ]);
                $service = app(RefundService::class);
                $refund = $service->create($order, $admin, [
                    'key' => 'demo-financial:'.$scenario, 'reason' => 'Financial demonstration: '.$scenario,
                    'items' => [['id' => $item->id, 'quantity' => $scenario === 'partial' ? 1 : 2]],
                    'shipping' => $scenario === 'partial' ? [] : [['vendor_order_id' => $vo->id, 'amount' => 100]],
                ]);
                if ($scenario !== 'pending') {
                    $service->process($refund, $admin);
                }
            });
        }
    }
}
