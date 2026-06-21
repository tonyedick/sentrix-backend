<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Services;

use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Support\Enums\TripStatus;
use Illuminate\Support\Collection;

/**
 * Spatial queries over the PostGIS `geography` columns. Uses `ST_DWithin` (which
 * the GiST index accelerates) to filter, and `ST_Distance` to rank — both on
 * `geography`, so the radius and distances are in metres.
 */
final readonly class ProximityService
{
    /**
     * Active trips within $radiusMeters of a point, nearest first, each with a
     * `distance_m` attribute.
     *
     * @return Collection<int, Trip>
     */
    public function nearbyActiveTrips(
        Organization $organization,
        float $lat,
        float $lng,
        int $radiusMeters,
        int $limit = 50,
        ?string $excludeTripId = null,
    ): Collection {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        return Trip::query()
            ->selectRaw("trips.*, ST_Distance(last_location, {$point}) AS distance_m", [$lng, $lat])
            ->where('organization_id', $organization->getKey())
            ->whereIn('status', [TripStatus::Active->value, TripStatus::Overdue->value])
            ->whereNotNull('last_location')
            ->when($excludeTripId !== null, fn ($query) => $query->whereKeyNot($excludeTripId))
            ->whereRaw("ST_DWithin(last_location, {$point}, ?)", [$lng, $lat, $radiusMeters])
            ->orderBy('distance_m')
            ->limit($limit)
            ->get();
    }
}
