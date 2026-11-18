<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use App\Services\IGeocodingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_it_is_bound_to_the_interface(): void
    {
        $this->assertInstanceOf(GeocodingService::class, app(IGeocodingService::class));
    }

    public function test_a_hit_returns_coordinates_and_is_cached(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '7.4478', 'lon' => '125.8078']])]);
        $geocoder = app(IGeocodingService::class);

        $this->assertSame(['lat' => 7.4478, 'lng' => 125.8078], $geocoder->geocode('Purok 3', 'Tagum City', 'Davao del Norte'));
        // Same query, different spacing/case: served from cache.
        $this->assertSame(['lat' => 7.4478, 'lng' => 125.8078], $geocoder->geocode(' purok 3 ', 'TAGUM CITY', 'Davao del Norte'));

        Http::assertSentCount(1);
    }

    // (Separate tests: stacked Http::fake() calls would keep matching the first stub.)

    public function test_an_empty_result_returns_null(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->assertNull(app(IGeocodingService::class)->geocode('Nowhere', 'Tagum City', 'Davao del Norte'));
    }

    public function test_a_server_error_returns_null(): void
    {
        Http::fake(['*' => Http::response('Server error', 500)]);

        $this->assertNull(app(IGeocodingService::class)->geocode('Error street', 'Tagum City', 'Davao del Norte'));
    }

    public function test_a_connection_failure_returns_null_instead_of_throwing(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertNull(app(IGeocodingService::class)->geocode('Timeout street', 'Tagum City', 'Davao del Norte'));
    }
}
