<?php

declare(strict_types=1);

namespace App\Domains\Trip\Contracts;

/**
 * Computes the base distance + duration between two points. The built-in driver
 * uses great-circle distance and an assumed speed; swap for a real routing
 * engine (OSRM / Mapbox / Google) via config('sentrix.routing.driver').
 */
interface RouteProvider
{
    /**
     * @return array{distance_m:int,duration_s:int}
     */
    public function estimate(float $originLat, float $originLng, float $destLat, float $destLng): array;
}
