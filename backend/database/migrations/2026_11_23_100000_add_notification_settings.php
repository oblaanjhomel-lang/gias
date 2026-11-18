<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // In-app notifications always; email too while this is on (some mails are mandatory).
            $table->boolean('email_notifications')->default(true)->after('status');
        });

        // Scheduled reminders: one row per (rule, recipient, window) so they don't repeat.
        Schema::create('notification_dedupes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dedupes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_notifications');
        });
    }
};
