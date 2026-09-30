<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $t) {
            // Legacy requests without items remain visible to their owner and admin only.
            $t->foreignId('vendor_order_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->boolean('is_returnable')->default(true));
        Schema::table('return_items', function (Blueprint $t) {
            $t->unsignedInteger('inspected_quantity')->nullable();
            $t->text('inspection_note')->nullable();
            $t->string('resolution')->nullable();
            $t->foreignId('inventory_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->timestamp('restocked_at')->nullable();
        });
        Schema::table('return_histories', fn (Blueprint $t) => $t->string('actor_role')->nullable());
        Schema::create('return_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('path');
            $t->string('disk');
            $t->string('mime_type', 80);
            $t->unsignedInteger('size');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_evidence');
        Schema::table('return_histories', fn (Blueprint $t) => $t->dropColumn('actor_role'));
        Schema::table('return_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('inventory_movement_id');
            $t->dropColumn(['inspected_quantity', 'inspection_note', 'resolution', 'restocked_at']);
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('is_returnable'));
        Schema::table('returns', fn (Blueprint $t) => $t->dropConstrainedForeignId('vendor_order_id'));
    }
};
