<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // What a partner needs: 1 = preferred, 2 = required.
        Schema::create('partner_skill', function (Blueprint $table) {
            $table->foreignId('industry_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('importance');
            $table->unique(['industry_partner_id', 'skill_id']);
        });

        // What a student has: 1 = beginner, 2 = intermediate, 3 = advanced.
        Schema::create('student_skill', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->unique(['student_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_skill');
        Schema::dropIfExists('partner_skill');
        Schema::dropIfExists('skills');
    }
};
