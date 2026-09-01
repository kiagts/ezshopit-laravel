<?php

use App\Enums\CommissionAdjustmentType;
use App\Enums\CommissionRuleScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->index();
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('rate_basis_points');
            $table->boolean('include_shipping')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['scope', 'vendor_id', 'is_active']);
            $table->index(['scope', 'is_active']);
            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('vendor_order_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_order_id')->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedSmallInteger('rate_basis_points');
            $table->boolean('include_shipping')->default(false);
            $table->unsignedBigInteger('original_items_amount');
            $table->unsignedBigInteger('original_shipping_amount');
            $table->unsignedBigInteger('original_commissionable_amount');
            $table->unsignedBigInteger('original_commission_amount');
            $table->string('currency', 3);
            $table->timestamp('recognized_at')->index();
            $table->timestamps();

            $table->index('commission_rule_id');
        });

        Schema::create('commission_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_order_commission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('refund_allocation_id')->nullable()->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('type')->default(CommissionAdjustmentType::Refund->value)->index();
            $table->bigInteger('commissionable_amount_delta');
            $table->bigInteger('commission_amount_delta');
            $table->string('reason');
            $table->timestamps();

            $table->index('vendor_order_commission_id');
            $table->index('refund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_adjustments');
        Schema::dropIfExists('vendor_order_commissions');
        Schema::dropIfExists('commission_rules');
    }
};
