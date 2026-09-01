<?php

use App\Enums\ShippingMethodType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('description', 500)->nullable();
            $table->string('type')->default(ShippingMethodType::FlatRate->value);
            $table->unsignedBigInteger('flat_rate')->default(0);
            $table->unsignedBigInteger('free_shipping_threshold')->nullable();
            $table->unsignedSmallInteger('min_delivery_days')->nullable();
            $table->unsignedSmallInteger('max_delivery_days')->nullable();
            $table->boolean('ships_to_all_supported_countries')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'code']);
            $table->index(['vendor_id', 'is_active', 'sort_order']);
        });

        Schema::create('shipping_method_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->timestamps();

            $table->unique(['shipping_method_id', 'country_code']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_countries');
        Schema::dropIfExists('shipping_methods');
    }
};
