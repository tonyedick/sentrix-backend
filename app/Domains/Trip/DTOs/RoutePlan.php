<?php

declare(strict_types=1);

namespace App\Domains\Trip\DTOs;

/**
 * A candidate route between two points for a given profile (safest / fastest).
 * `safetyScore` is 0–100; `alertsCount` is active community alerts along the
 * corridor. Geometry (polyline) is left for a real routing provider.
 */
final readonly class RoutePlan
{
    public function __construct(
        public string $profile,
        public int $distanceM,
        public int $durationS,
        public int $safetyScore,
        public int $alertsCount,
        public bool $recommended = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile' => $this->profile,
            'distance_m' => $this->distanceM,
            'duration_s' => $this->durationS,
            'safety_score' => $this->safetyScore,
            'alerts_count' => $this->alertsCount,
            'recommended' => $this->recommended,
        ];
    }
}
