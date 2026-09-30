<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('refunds', function (Blueprint $t) {
            $t->foreignId('return_request_id')->nullable()->constrained('returns')->restrictOnDelete();
            $t->foreignId('vendor_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('idempotency_key', 100)->nullable()->unique();
            $t->string('request_hash', 64)->nullable();
            $t->timestamp('economics_posted_at')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('refund_allocations', function (Blueprint $t) {
            $t->foreignId('return_item_id')->nullable()->constrained()->restrictOnDelete();
            foreach (['gross_amount','discount_amount','merchandise_amount','tax_amount'] as $column) $t->unsignedBigInteger($column)->nullable();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->unsignedBigInteger('tax_total')->nullable());
        Schema::create('refund_funding_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('refund_id')->constrained()->restrictOnDelete();
            $t->string('source_type', 30);
            $t->unsignedBigInteger('source_id');
            $t->foreignId('payment_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount');
            $t->char('currency', 3);
            $t->string('status', 30);
            $t->string('idempotency_key', 120)->unique();
            $t->string('external_reference')->nullable();
            $t->unsignedBigInteger('transaction_id')->nullable();
            $t->timestamps();
            $t->unique(['refund_id','source_type','source_id'], 'refund_funding_source_unique');
        });
    }
    public function down(): void {
        Schema::dropIfExists('refund_funding_allocations');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('tax_total'));
        Schema::table('refund_allocations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('return_item_id');
            $t->dropColumn(['gross_amount','discount_amount','merchandise_amount','tax_amount']);
        });
        Schema::table('refunds', function (Blueprint $t) {
            $t->dropConstrainedForeignId('return_request_id'); $t->dropConstrainedForeignId('vendor_order_id'); $t->dropConstrainedForeignId('processed_by');
            $t->dropColumn(['idempotency_key','request_hash','economics_posted_at']);
        });
    }
};

