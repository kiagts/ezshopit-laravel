<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            // $table->index(['is_active', 'sort_order']);
        });

        Schema::create('product_attribute_set_attributes', function (Blueprint $table): void {
            $table->id();
            
            $table->foreignId('product_attribute_set_id')
                ->constrained('product_attribute_sets', indexName: 'pasa_product_attr_set_id_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes', indexName: 'pasa_product_attr_id_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['product_attribute_set_id', 'product_attribute_id'], 'pas_attribute_unique');
            // $table->index(['product_attribute_set_id', 'sort_order']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('product_attribute_set_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('product_attribute_sets', indexName: 'categories_product_attr_set_id_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign('categories_product_attr_set_id_fk');
            $table->dropColumn('product_attribute_set_id');
        });

        Schema::dropIfExists('product_attribute_set_attributes');
        Schema::dropIfExists('product_attribute_sets');
    }
};