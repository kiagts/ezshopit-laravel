<?php

namespace App\Services\Refunds;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundAllocation;
use Brick\Math\BigInteger;
use Illuminate\Validation\ValidationException;

class RefundCalculator
{
    public function historicalTaxes(Order $order): array
    {
        $items = OrderItem::whereHas('vendorOrder', fn ($q) => $q->where('order_id', $order->id))->orderBy('id')->get();
        $known = (int) $items->whereNotNull('tax_total')->sum('tax_total');
        $unknown = $items->whereNull('tax_total');
        $remaining = (int) $order->tax_total - $known;
        if ($remaining < 0) {
            $this->invalid('Historical tax snapshots are inconsistent.');
        }
        $weights = max(1, (int) $unknown->sum(fn ($item) => $item->customerLineTotal()));
        $cumulative = 0;
        $allocated = 0;
        $out = [];
        foreach ($items as $item) {
            if ($item->tax_total !== null) {
                $out[$item->id] = (int) $item->tax_total;

                continue;
            }
            $cumulative += $item->customerLineTotal();
            $end = $unknown->last()->id === $item->id ? $remaining : $this->proportion($remaining, $cumulative, $weights);
            $out[$item->id] = $end - $allocated;
            $allocated = $end;
        }

        return $out;
    }

    public function item(OrderItem $item, int $quantity, ?int $returnItemId = null): array
    {
        $prior = RefundAllocation::where('order_item_id', $item->id)->whereHas('refund', fn ($q) => $q->whereNotIn('status', ['failed', 'cancelled']))->get();
        $remainingQty = $item->quantity - (int) $prior->sum('quantity');
        if ($quantity < 1 || $quantity > $remainingQty) {
            $this->invalid('Refund quantity exceeds remaining purchased quantity.');
        }
        $tax = $this->historicalTaxes($item->vendorOrder->order)[$item->id] ?? 0;
        $remainingNet = $item->customerLineTotal() - (int) $prior->sum(fn ($a) => $a->merchandise_amount ?? $a->amount);
        $remainingTax = $tax - (int) $prior->sum('tax_amount');
        $remainingDiscount = (int) $item->discount_total - (int) $prior->sum('discount_amount');
        $remainingSeller = $item->sellerLineTotal() - (int) $prior->sum(fn ($a) => $a->seller_amount ?? $a->amount);
        if (min($remainingNet, $remainingTax, $remainingDiscount, $remainingSeller) < 0) {
            $this->invalid('Historical item refund capacity is inconsistent.');
        }
        $share = fn (int $amount) => $quantity === $remainingQty ? $amount : $this->proportion($amount, $quantity, $remainingQty);
        $net = $share($remainingNet);
        $discount = $share($remainingDiscount);
        $taxAmount = $share($remainingTax);

        return ['type' => 'item', 'order_item_id' => $item->id, 'vendor_order_id' => $item->vendor_order_id, 'return_item_id' => $returnItemId,
            'quantity' => $quantity, 'gross_amount' => $net + $discount, 'discount_amount' => $discount,
            'merchandise_amount' => $net, 'tax_amount' => $taxAmount, 'amount' => $net + $taxAmount, 'seller_amount' => $share($remainingSeller)];
    }

    private function proportion(int $amount, int $numerator, int $denominator): int
    {
        return BigInteger::of($amount)->multipliedBy($numerator)->quotient($denominator)->toInt();
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}
