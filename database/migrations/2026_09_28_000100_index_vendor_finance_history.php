<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_ledger_entries', fn (Blueprint $t) => $t->index(['vendor_id', 'currency', 'created_at', 'id'], 'ledger_vendor_currency_history'));
    }

    public function down(): void
    {
        Schema::table('vendor_ledger_entries', fn (Blueprint $t) => $t->dropIndex('ledger_vendor_currency_history'));
    }
};
