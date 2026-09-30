<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('category', 32);
            $t->boolean('database_enabled')->default(true);
            $t->boolean('email_enabled')->default(true);
            $t->timestamps();
            $t->unique(['user_id', 'category']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->string('notification_locale', 12)->nullable());
        Schema::table('notification_dispatches', function (Blueprint $t) {
            $t->text('payload')->nullable();
            $t->string('status', 24)->default('legacy');
            $t->timestamp('database_sent_at')->nullable();
            $t->timestamp('mail_sent_at')->nullable();
            $t->string('last_error', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notification_locale'));
        Schema::table('notification_dispatches', fn (Blueprint $t) => $t->dropColumn(['payload', 'status', 'database_sent_at', 'mail_sent_at', 'last_error']));
    }
};
