<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('placed_at')->index();
            $table->timestamp('cancelled_at')->nullable()->after('completed_at')->index();
        });

        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('shipping_max_delivery_days');
            $table->timestamp('processing_at')->nullable()->after('confirmed_at');
            $table->timestamp('ready_to_ship_at')->nullable()->after('processing_at');
            $table->timestamp('shipped_at')->nullable()->after('ready_to_ship_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at')->index();
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'confirmed_at',
                'processing_at',
                'ready_to_ship_at',
                'shipped_at',
                'delivered_at',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
            ]);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['completed_at', 'cancelled_at']);
        });
    }
};
