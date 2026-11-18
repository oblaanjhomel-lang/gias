<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Safety net: PostGIS is already enabled on the gias database.
     */
    public function up(): void
    {
        // Lets the suite also run on SQLite (e.g. a quick in-memory check); PostGIS is pgsql-only.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    /**
     * Intentionally a no-op: never drop PostGIS (spatial columns depend on it).
     */
    public function down(): void
    {
        //
    }
};
