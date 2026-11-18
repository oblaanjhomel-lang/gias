<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_partner_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active'); // active | completed | withdrawn
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['industry_partner_id', 'status']);
        });

        // A student has at most ONE active deployment (Postgres partial unique index).
        DB::statement(
            "CREATE UNIQUE INDEX deployments_one_active_per_student ON deployments (student_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments'); // drops its indexes too
    }
};
