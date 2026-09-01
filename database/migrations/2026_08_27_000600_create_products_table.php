<?php

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default(ProductType::Simple->value)->index();
            $table->string('status')->default(ProductStatus::Draft->value)->index();
            $table->string('approval_status')->default(ProductApprovalStatus::Draft->value)->index();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
            $table->index(['category_id', 'approval_status']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('base_price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->string('combination_key');
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'combination_key']);
            $table->index(['product_id', 'is_default']);
        });

        Schema::create('product_variant_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants', indexName: 'pvav_product_variant_id_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes', indexName: 'pvav_product_attribute_id_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('product_attribute_value_id')
                ->constrained('product_attribute_values', indexName: 'pvav_product_attribute_val_id_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['product_variant_id', 'product_attribute_id'], 'variant_one_value_per_attribute');
            $table->unique(['product_variant_id', 'product_attribute_value_id'], 'variant_value_unique');
        });

        Schema::create('product_specifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->text('text_value')->nullable();
            $table->decimal('number_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'product_attribute_id']);
        });

        Schema::create('product_specification_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_specification_id')
                ->constrained('product_specifications', indexName: 'psv_product_spec_id_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_value_id')
                ->constrained('product_attribute_values', indexName: 'psv_product_attribute_val_id_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['product_specification_id', 'product_attribute_value_id'], 'specification_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specification_values');
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};