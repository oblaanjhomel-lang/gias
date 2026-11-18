<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            // Always computed by the server from the deployment's start_date and week_number.
            $table->date('period_start');
            $table->date('period_end');
            $table->jsonb('daily_hours'); // {mon,tue,wed,thu,fri,sat,sun}: 0–12 in 0.5 steps
            $table->decimal('total_hours', 5, 2);
            $table->text('tasks');
            $table->text('learnings')->nullable();
            $table->string('status')->default('draft'); // draft | submitted | signed | returned
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->text('return_note')->nullable();
            $table->timestamps();

            $table->unique(['deployment_id', 'week_number']);
            $table->index('status');
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('recommendation_snapshot');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            // Reason when an admin completes below the required hours (force).
            $table->text('completion_note')->nullable()->after('completed_by');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['completed_at', 'completion_note']);
        });

        Schema::dropIfExists('weekly_reports');
    }
};
