<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('contact_number', 20)->nullable();
            $table->string('address'); // street / barangay
            $table->string('city');
            $table->string('province');
            $table->magellanPoint('location', 4326)->nullable();
            $table->string('geocode_status')->default('pending');
            $table->unsignedSmallInteger('intern_slots')->default(0);
            $table->timestamps();

            $table->spatialIndex('location'); // GIST
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_partners');
    }
};
