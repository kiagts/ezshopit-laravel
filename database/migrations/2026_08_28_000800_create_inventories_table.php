<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultThreshold = (int) config('marketplace.inventory.default_low_stock_threshold', 5);

        Schema::create('inventories', function (Blueprint $table) use ($defaultThreshold): void {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('on_hand')->default(0)->index();
            $table->unsignedBigInteger('reserved')->default(0)->index();
            $table->unsignedBigInteger('low_stock_threshold')->default($defaultThreshold);
            $table->timestamps();
        });

        $now = now();
        DB::table('product_variants')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select('id')
            ->get()
            ->each(function (object $variant) use ($now, $defaultThreshold): void {
                DB::table('inventories')->updateOrInsert(
                    ['product_variant_id' => $variant->id],
                    [
                        'on_hand' => 0,
                        'reserved' => 0,
                        'low_stock_threshold' => $defaultThreshold,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
