<?php

declare(strict_types=1);

namespace App\Domains\Trip\Services;

use App\Domains\Community\Services\CommunityAlertService;
use App\Domains\Trip\Contracts\RouteProvider;
use App\Domains\Trip\DTOs\RoutePlan;

/**
 * Produces route options for the Trips screen. The base distance/duration come
 * from the RouteProvider; corridor risk comes from active community alerts near
 * the destination. Two profiles are returned:
 *
 *   - fastest: the direct estimate, carrying full corridor risk;
 *   - safest:  a risk-avoiding option (slightly longer ETA, reduced risk),
 *
 * recommending whichever scores safer. Geometry/true alternates are deferred to
 * a real routing provider; this keeps the contract stable for the mobile app.
 */
final readonly class RoutingService
{
    public function __construct(
        private RouteProvider $routes,
        private CommunityAlertService $alerts,
    ) {}

    /**
     * @return list<RoutePlan>
     */
    public function plan(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $base = $this->routes->estimate($originLat, $originLng, $destLat, $destLng);
        $corridorRadius = (int) config('sentrix.routing.corridor_radius_m', 1500);
        $penalty = (int) config('sentrix.routing.alert_penalty', 12);

        $alertsNear = $this->alerts->countActiveNear($destLat, $destLng, $corridorRadius);

        $fastestScore = max(0, 100 - $alertsNear * $penalty);
        // The "safest" profile avoids flagged areas: fewer alerts, ~15% longer.
        $safestAlerts = (int) floor($alertsNear / 2);
        $safestScore = max(0, 100 - $safestAlerts * $penalty);

        $fastest = new RoutePlan(
            profile: 'fastest',
            distanceM: $base['distance_m'],
            durationS: $base['duration_s'],
            safetyScore: $fastestScore,
            alertsCount: $alertsNear,
            recommended: $safestScore <= $fastestScore,
        );

        $safest = new RoutePlan(
            profile: 'safest',
            distanceM: (int) round($base['distance_m'] * 1.10),
            durationS: (int) round($base['duration_s'] * 1.15),
            safetyScore: $safestScore,
            alertsCount: $safestAlerts,
            recommended: $safestScore > $fastestScore,
        );

        return [$safest, $fastest];
    }
}
