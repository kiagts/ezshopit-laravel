<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('cancelled_at')->index();
            $table->timestamp('requires_review_at')->nullable()->after('expires_at')->index();
            $table->string('review_reason')->nullable()->after('requires_review_at');

            $table->index(['method', 'status', 'expires_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('cancellation_source')->nullable()->after('cancelled_at')->index();
            $table->string('cancellation_reason', 500)->nullable()->after('cancellation_source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_source', 'cancellation_reason']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['method', 'status', 'expires_at']);
            $table->dropColumn(['expires_at', 'requires_review_at', 'review_reason']);
        });
    }
};
