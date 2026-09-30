<?php

namespace App\Services\Documents;

use App\Models\Order;
use App\Models\Refund;
use App\Models\VendorOrder;

class DocumentSnapshot
{
    public function recipient(Order $order): array
    {
        return ['name' => trim($order->shipping_first_name.' '.$order->shipping_last_name), 'address' => array_values(array_filter([$order->shipping_address_line_1, $order->shipping_address_line_2, trim($order->shipping_city.' '.$order->shipping_state_region.' '.$order->shipping_postal_code), $order->shipping_country_code]))];
    }

    public function order(Order $o, ?VendorOrder $seller = null): array
    {
        $groups = $seller ? collect([$seller]) : $o->vendorOrders()->with(['items', 'vendor'])->orderBy('id')->get();
        $source = $seller ?? $o;
        $content = ['order_reference' => $o->order_number, 'order_date' => $o->placed_at?->toIso8601String(), 'seller_reference' => $seller?->vendor_order_number, 'status' => $seller?->status->value ?? $o->status->value, 'payment_status' => $seller ? null : ($o->payment?->status->value ?? 'unconfirmed'), 'groups' => [], 'totals' => ['subtotal' => (int) $source->items_subtotal, 'discount' => (int) $source->discount_total, 'shipping' => (int) $source->shipping_total, 'tax' => (int) $source->tax_total, 'total' => (int) $source->grand_total]];
        foreach ($groups as $vo) {
            $lines = [];
            foreach ($vo->items as $i) {
                $lines[] = ['name' => $i->product_name, 'variant' => $i->variant_description, 'sku' => $i->sku, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price, 'gross' => $i->line_total, 'discount' => $i->discount_total, 'tax' => $i->tax_total, 'net' => $i->customerLineTotal()];
            }
            $content['groups'][] = ['seller' => $vo->vendor->display_name, 'reference' => $vo->vendor_order_number, 'shipping_method' => $vo->shipping_method_name, 'lines' => $lines];
        }
        if ($seller) {
            $content['seller_tax_note'] = true;
        } else {
            $content['funding'] = ['gift_card' => (int) $o->gift_card_applied_total, 'wallet' => (int) $o->wallet_applied_total, 'external' => max(0, (int) $o->grand_total - (int) $o->gift_card_applied_total - (int) $o->wallet_applied_total)];
        }

        return $content;
    }

    public function refund(Refund $r, string $invoice): array
    {
        $r->load(['allocations.orderItem', 'fundingAllocations']);
        $rows = [];
        $totals = ['merchandise' => 0, 'tax' => 0, 'shipping' => 0, 'adjustment' => 0, 'total' => (int) $r->amount];
        foreach ($r->allocations as $a) {
            $item = $a->orderItem;
            $type = $a->type->value;
            $net = (int) ($a->merchandise_amount ?? $a->amount);
            $tax = (int) ($a->tax_amount ?? 0);
            if ($type === 'item') {
                $totals['merchandise'] += $net;
                $totals['tax'] += $tax;
            } elseif ($type === 'shipping') {
                $totals['shipping'] += (int) $a->amount;
            } else {
                $totals['adjustment'] += (int) $a->amount;
            }
            $rows[] = ['name' => $item?->product_name ?? $type, 'variant' => $item?->variant_description, 'sku' => $item?->sku, 'quantity' => $a->quantity, 'ordered_quantity' => $item?->quantity, 'gross' => $a->gross_amount, 'discount' => $a->discount_amount, 'net' => $net, 'tax' => $tax, 'total' => $a->amount, 'type' => $type];
        }

        return ['order_reference' => $r->order->order_number, 'order_date' => $r->order->placed_at?->toIso8601String(), 'invoice_reference' => $invoice, 'refund_reference' => $r->refund_number, 'refund_date' => $r->processed_at?->toIso8601String(), 'status' => $r->status->value, 'lines' => $rows, 'totals' => $totals, 'destinations' => $r->fundingAllocations->map(fn ($a) => ['label' => $a->source_type->label(), 'amount' => $a->amount, 'status' => $a->status->value])->all()];
    }
}
