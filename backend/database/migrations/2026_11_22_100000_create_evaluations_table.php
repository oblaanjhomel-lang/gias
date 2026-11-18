<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // midterm | final
            $table->jsonb('ratings'); // {criterion_key: 1–5}; may be partial while draft
            $table->jsonb('criteria_snapshot')->nullable(); // [{key, label}] captured at submit
            $table->decimal('partner_mean', 3, 2)->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default('draft'); // draft | submitted | returned | reviewed
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('coordinator_rating')->nullable(); // 1–5
            $table->text('coordinator_note')->nullable();
            $table->decimal('final_rating', 3, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('return_note')->nullable();
            $table->timestamps();

            $table->unique(['deployment_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
