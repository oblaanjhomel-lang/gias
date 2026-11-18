<?php

namespace App\Services;

interface IGeocodingService
{
    /**
     * Look up coordinates for an address in the Davao Region (config('gias.region_bounds')).
     * Never throws: returns null when the address can't be found or the lookup fails.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address, string $city, string $province): ?array;
}
