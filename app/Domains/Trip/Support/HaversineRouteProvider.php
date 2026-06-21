<?php

declare(strict_types=1);

namespace App\Domains\Trip\Support;

use App\Domains\Trip\Contracts\RouteProvider;

/**
 * Built-in routing estimate: great-circle distance + an assumed average speed.
 * A placeholder for a real routing engine — good enough to power ETA/distance
 * in the Trips screen without an external dependency.
 */
final class HaversineRouteProvider implements RouteProvider
{
    private const EARTH_RADIUS_M = 6_371_000;

    /**
     * @return array{distance_m:int,duration_s:int}
     */
    public function estimate(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $speedKmh = (float) config('sentrix.routing.assumed_speed_kmh', 40);

        $latFrom = deg2rad($originLat);
        $latTo = deg2rad($destLat);
        $deltaLat = deg2rad($destLat - $originLat);
        $deltaLng = deg2rad($destLng - $originLng);

        $a = sin($deltaLat / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = (int) round(self::EARTH_RADIUS_M * $c);

        $duration = $speedKmh > 0 ? (int) round($distance / ($speedKmh * 1000 / 3600)) : 0;

        return ['distance_m' => $distance, 'duration_s' => $duration];
    }
}
