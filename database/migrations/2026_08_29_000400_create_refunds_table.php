<?php

use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('review_resolved_at')->nullable()->after('review_reason')->index();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status')->default(RefundStatus::Pending->value)->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('provider')->index();
            $table->string('provider_reference')->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('requested_at')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['payment_id', 'status']);
        });

        Schema::create('refund_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('vendor_order_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('type')->default(RefundAllocationType::Adjustment->value)->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('quantity')->nullable();
            $table->timestamps();

            $table->index('vendor_order_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_allocations');
        Schema::dropIfExists('refunds');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('review_resolved_at');
        });
    }
};
