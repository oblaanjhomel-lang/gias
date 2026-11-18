<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single row: the Weighted Sum Model weights and scales.
        Schema::create('recommendation_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weight_coverage')->default(40);
            $table->unsignedTinyInteger('weight_proficiency')->default(25);
            $table->unsignedTinyInteger('weight_proximity')->default(20);
            $table->unsignedTinyInteger('weight_slots')->default(15);
            $table->unsignedSmallInteger('proximity_zero_km')->default(100);
            $table->unsignedSmallInteger('slot_saturation')->default(5);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed the default row so the settings always exist.
        DB::table('recommendation_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_settings');
    }
};
