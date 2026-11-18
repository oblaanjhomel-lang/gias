<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->string('algorithm_used')->nullable()->after('assigned_by'); // cos | wsm | fuzzy | manual
            $table->unsignedSmallInteger('rank_cos')->nullable()->after('algorithm_used');
            $table->unsignedSmallInteger('rank_wsm')->nullable()->after('rank_cos');
            $table->unsignedSmallInteger('rank_fuzzy')->nullable()->after('rank_wsm');
            $table->jsonb('recommendation_snapshot')->nullable()->after('rank_fuzzy');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['algorithm_used', 'rank_cos', 'rank_wsm', 'rank_fuzzy', 'recommendation_snapshot']);
        });
    }
};
