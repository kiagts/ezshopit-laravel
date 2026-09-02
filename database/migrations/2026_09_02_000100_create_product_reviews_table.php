<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->string('status');
            $table->boolean('verified_purchase')->default(true);
            $table->string('reviewer_display_name')->nullable();
            $table->string('variant_snapshot')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('moderation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'product_id']);
            $table->index('user_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('rating');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
