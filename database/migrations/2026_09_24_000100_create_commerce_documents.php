<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_documents', function (Blueprint $t) {
            $t->id();
            $t->string('document_type', 30);
            $t->string('document_number', 60)->unique();
            $t->string('logical_key', 100)->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('vendor_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('currency_code', 3);
            $t->unsignedTinyInteger('minor_unit');
            $t->string('locale', 12);
            $t->string('issuer_type', 20);
            $t->json('issuer_snapshot');
            $t->json('recipient_snapshot');
            $t->json('content');
            $t->timestamp('issued_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_documents');
    }
};
