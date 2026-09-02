<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('payout_number')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->foreignId('payout_method_id')->nullable()->constrained('vendor_payout_methods')->cascadeOnUpdate()->nullOnDelete();
            $table->string('payout_method_type')->nullable();
            $table->text('destination_snapshot')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('external_reference')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('status');
            $table->index('currency');
            $table->index('requested_at');
            $table->index('paid_at');
            $table->index(['vendor_id', 'currency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payouts');
    }
};
