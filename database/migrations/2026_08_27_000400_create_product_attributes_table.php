<?php

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default(ProductAttributeType::Specification->value)->index();
            $table->string('input_type')->default(ProductAttributeInputType::Text->value)->index();
            $table->boolean('is_filterable')->default(false)->index();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes', indexName: 'pav_product_attribute_id_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('value');
            $table->string('code');
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_attribute_id', 'code']);
            $table->index(['product_attribute_id', 'is_active', 'sort_order'], 'pav_attr_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};