<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number', 20)->unique();
            $table->unsignedTinyInteger('year_level'); // 1–5
            $table->string('contact_number', 20);
            $table->string('address'); // street / barangay
            $table->string('city');
            $table->string('province');
            $table->magellanPoint('home_location', 4326)->nullable();
            $table->string('geocode_status')->default('pending');
            $table->timestamps();

            $table->spatialIndex('home_location'); // GIST
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
