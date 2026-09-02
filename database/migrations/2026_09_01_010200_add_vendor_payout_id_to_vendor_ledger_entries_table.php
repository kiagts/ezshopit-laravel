<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_ledger_entries', function (Blueprint $table): void {
            $table->foreignId('vendor_payout_id')
                ->nullable()
                ->after('commission_adjustment_id')
                ->constrained('vendor_payouts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ledger_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vendor_payout_id');
        });
    }
};
