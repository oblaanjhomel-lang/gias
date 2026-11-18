<?php

namespace Tests\Feature;

use App\Services\HealthService;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_versions(): void
    {
        $this->mock(HealthService::class)
            ->shouldReceive('check')
            ->once()
            ->andReturn([
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'postgis' => 'POSTGIS="3.7.0"',
            ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'ok',
                    'laravel' => app()->version(),
                    'php' => PHP_VERSION,
                    'postgis' => 'POSTGIS="3.7.0"',
                ],
            ]);
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->mock(HealthService::class)
            ->shouldReceive('check')
            ->andReturn(['laravel' => '', 'php' => '', 'postgis' => '']);

        $this->getJson('/api/health')->assertOk();
    }
}
