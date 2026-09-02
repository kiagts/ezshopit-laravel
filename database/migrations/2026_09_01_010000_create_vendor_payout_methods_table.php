<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payout_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('account_number')->nullable();
            $table->text('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->text('routing_number')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_methods');
    }
};
