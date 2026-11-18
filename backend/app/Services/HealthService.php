<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HealthService
{
    /**
     * @return array{laravel: string, php: string, postgis: string}
     */
    public function check(): array
    {
        return [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'postgis' => DB::scalar('SELECT postgis_full_version()'),
        ];
    }
}
