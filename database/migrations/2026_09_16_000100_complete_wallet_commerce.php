<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_applied_total')->default(0);
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->index(['reference_type', 'reference_id'], 'wallet_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
            $table->dropColumn('metadata');
            $table->dropIndex('wallet_reference_idx');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('wallet_applied_total'));
    }
};
