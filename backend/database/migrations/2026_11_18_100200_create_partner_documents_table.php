<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_partner_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // sec_dti | business_permit | moa_draft | other
            $table->string('original_name');
            $table->string('path'); // on the private `local` disk
            $table->unsignedInteger('size'); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_documents');
    }
};
