<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('algorithm'); // cos | wsm | fuzzy
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('comment')->nullable();
            // Nullable so a coordinator who left ratings can still be deleted.
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('snapshot'); // that algorithm's ranked list when rated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_feedback');
    }
};
