<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $t) {
            $t->foreignId('vendor_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('return_request_id')->nullable()->constrained('returns')->restrictOnDelete();
            $t->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('resolution_refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $t->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('resolution')->nullable();
            $t->text('resolution_note')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->string('resolution_hash', 64)->nullable();
            $t->string('active_key', 64)->nullable()->unique();
            $t->index(['vendor_order_id', 'status']);
        });
        Schema::table('dispute_messages', fn (Blueprint $t) => $t->string('author_role')->nullable());
        Schema::create('dispute_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispute_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_role')->nullable();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('note')->nullable();
            $t->string('resolution')->nullable();
            $t->timestamps();
        });
        Schema::create('dispute_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispute_message_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('disk');
            $t->string('path')->unique();
            $t->string('mime_type');
            $t->unsignedInteger('size');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('dispute_histories');
        Schema::table('dispute_messages', fn (Blueprint $t) => $t->dropColumn('author_role'));
        Schema::table('disputes', function (Blueprint $t) {
            foreach (['vendor_order_id', 'return_request_id', 'refund_id', 'resolution_refund_id', 'assigned_admin_id', 'resolved_by'] as $c) {
                $t->dropConstrainedForeignId($c);
            }
            $t->dropColumn(['resolution', 'resolution_note', 'resolved_at', 'last_activity_at', 'resolution_hash', 'active_key']);
        });
    }
};
