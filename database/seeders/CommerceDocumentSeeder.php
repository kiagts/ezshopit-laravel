<?php

namespace Database\Seeders;

use App\Enums\CommerceDocumentType as Type;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Services\Documents\DocumentService;
use Illuminate\Database\Seeder;

class CommerceDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(DocumentService::class);
        // Only existing demo commerce; never create orders, payments or refunds here.
        $demo = Order::whereHas('user', fn ($q) => $q->where('email', 'customer@example.com'));
        $orders = (clone $demo)->orderBy('id')->limit(8)->get()
            ->merge((clone $demo)->has('vendorOrders', '>', 1)->limit(3)->get())
            ->merge((clone $demo)->whereHas('refunds', fn ($q) => $q->where('refunds.status', RefundStatus::Succeeded))->limit(3)->get());
        $orders->load(['vendorOrders', 'refunds']);
        $orders->each(function ($order) use ($service) {
            $service->issue(Type::OrderInvoice, $order);
            $service->issue(Type::OrderSummary, $order);
            foreach ($order->vendorOrders as $seller) {
                $service->issue(Type::VendorOrder, $seller);
            }
            foreach ($order->refunds as $refund) {
                if ($refund->status === RefundStatus::Succeeded) {
                    $service->issue(Type::CreditNote, $refund);
                }
            }
        });
    }
}
