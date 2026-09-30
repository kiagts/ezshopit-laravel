<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('funding_source')->index();
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('discount_type')->index();
            $table->unsignedInteger('discount_value');
            $table->unsignedBigInteger('minimum_spend')->nullable();
            $table->unsignedBigInteger('maximum_discount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'funding_source']);
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('promotion_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('promotion_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->after('token')->constrained()->cascadeOnUpdate()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_total')->default(0)->after('items_subtotal');
        });

        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_total')->default(0)->after('items_subtotal');
            $table->unsignedBigInteger('marketplace_discount_total')->default(0)->after('discount_total');
            $table->unsignedBigInteger('vendor_discount_total')->default(0)->after('marketplace_discount_total');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_total')->default(0)->after('line_total');
            $table->unsignedBigInteger('marketplace_discount_total')->default(0)->after('discount_total');
            $table->unsignedBigInteger('vendor_discount_total')->default(0)->after('marketplace_discount_total');
            $table->unsignedBigInteger('net_line_total')->nullable()->after('vendor_discount_total');
        });

        Schema::table('refund_allocations', function (Blueprint $table): void {
            $table->unsignedBigInteger('seller_amount')->nullable()->after('amount');
        });

        Schema::create('promotion_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('code_snapshot');
            $table->string('funding_source');
            $table->unsignedBigInteger('discount_amount');
            $table->string('currency', 3);
            $table->timestamp('redeemed_at')->index();
            $table->timestamps();

            $table->index(['promotion_id', 'user_id']);
            $table->index(['promotion_id', 'redeemed_at']);
        });

        Schema::create('order_discount_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('vendor_order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('promotion_code');
            $table->string('promotion_name');
            $table->string('funding_source')->index();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->index(['order_id', 'promotion_id']);
            $table->index(['vendor_order_id', 'promotion_id']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_discount_allocations');
        Schema::dropIfExists('promotion_redemptions');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_total', 'marketplace_discount_total', 'vendor_discount_total', 'net_line_total']);
        });

        Schema::table('refund_allocations', function (Blueprint $table): void {
            $table->dropColumn('seller_amount');
        });

        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_total', 'marketplace_discount_total', 'vendor_discount_total']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('discount_total');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_id');
        });

        Schema::dropIfExists('promotion_categories');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotions');
    }
};
