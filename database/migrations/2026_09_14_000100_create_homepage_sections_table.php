<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('type');
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'starts_at', 'ends_at', 'sort_order']);
            $table->index(['type', 'sort_order']);
        });

        Schema::create('homepage_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->string('item_type');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('custom_title')->nullable();
            $table->text('custom_subtitle')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['homepage_section_id', 'item_type', 'item_id'], 'homepage_section_item_unique');
            $table->index(['homepage_section_id', 'item_type', 'sort_order'], 'homepage_section_item_order_index');
            $table->index(['item_type', 'item_id'], 'homepage_section_item_target_index');
        });

        Schema::create('homepage_section_content_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('mobile_image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('icon_key')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['homepage_section_id', 'sort_order'], 'homepage_content_item_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_section_content_items');
        Schema::dropIfExists('homepage_section_items');
        Schema::dropIfExists('homepage_sections');
    }
};
