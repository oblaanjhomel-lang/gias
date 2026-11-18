<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nominatim (OpenStreetMap) geocoder, restricted to the Davao Region.
 * Usage policy: https://operations.osmfoundation.org/policies/nominatim/
 */
class GeocodingService implements IGeocodingService
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    private const CACHE_DAYS = 30;

    public function geocode(string $address, string $city, string $province): ?array
    {
        $query = collect([$address, $city, $province, 'Philippines'])
            ->map(fn (string $part) => Str::squish($part))
            ->implode(', ');
        $cacheKey = 'geocode:'.sha1(Str::lower($query));

        try {
            if (is_array($cached = Cache::get($cacheKey))) {
                return $cached;
            }

            $result = $this->lookup($query);

            // Only hits are cached, so a failed lookup is retried on the next save.
            if ($result !== null) {
                Cache::put($cacheKey, $result, now()->addDays(self::CACHE_DAYS));
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning('Geocoding failed', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function lookup(string $query): ?array
    {
        $box = config('gias.region_bounds');

        $response = Http::withUserAgent('GIAS/1.0 ('.config('services.nominatim.email').')')
            ->acceptJson()
            ->timeout(10)
            ->get(self::URL, [
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'ph',
                'q' => $query,
                // left,top,right,bottom = min lng, max lat, max lng, min lat
                'viewbox' => implode(',', [$box['min_lng'], $box['max_lat'], $box['max_lng'], $box['min_lat']]),
                'bounded' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $hit = $response->json('0');

        if (! is_array($hit) || ! is_numeric($hit['lat'] ?? null) || ! is_numeric($hit['lon'] ?? null)) {
            return null;
        }

        return ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon']];
    }
}
