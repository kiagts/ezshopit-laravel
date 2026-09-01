<?php

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('method')->index();
            $table->string('provider')->index();
            $table->string('status')->default(PaymentStatus::Pending->value)->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('provider_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status')->default(PaymentAttemptStatus::Pending->value)->index();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['payment_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
