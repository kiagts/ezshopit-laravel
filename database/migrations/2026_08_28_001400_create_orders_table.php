<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->cascadeOnUpdate()->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('status')->default(OrderStatus::Pending->value)->index();
            $table->string('payment_status')->default(PaymentStatus::Pending->value)->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('items_subtotal');
            $table->unsignedBigInteger('shipping_total');
            $table->unsignedBigInteger('grand_total');
            $table->string('shipping_first_name');
            $table->string('shipping_last_name');
            $table->string('shipping_phone');
            $table->string('shipping_address_line_1');
            $table->string('shipping_address_line_2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_state_region')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->char('shipping_country_code', 2);
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('vendor_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->cascadeOnUpdate()->nullOnDelete();
            $table->string('vendor_order_number')->unique();
            $table->string('status')->default(VendorOrderStatus::Pending->value)->index();
            $table->unsignedBigInteger('items_subtotal');
            $table->unsignedBigInteger('shipping_total');
            $table->unsignedBigInteger('grand_total');
            $table->string('shipping_method_name');
            $table->string('shipping_method_code');
            $table->text('shipping_method_description')->nullable();
            $table->unsignedSmallInteger('shipping_min_delivery_days')->nullable();
            $table->unsignedSmallInteger('shipping_max_delivery_days')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
            $table->index(['order_id', 'vendor_id']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_description')->nullable();
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->json('attributes_snapshot')->nullable();
            $table->timestamps();

            $table->index('product_variant_id');
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default(InventoryReservationStatus::Active->value)->index();
            $table->timestamps();

            $table->index(['inventory_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('vendor_orders');
        Schema::dropIfExists('orders');
    }
};
