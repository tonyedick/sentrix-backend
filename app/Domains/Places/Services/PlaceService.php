<?php

declare(strict_types=1);

namespace App\Domains\Places\Services;

use App\Domains\Places\Models\Place;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Geo lookups over the POI directory. `nearby` ranks by distance (metres) using
 * the PostGIS `location` column; `openNow` filters by 24/7 or current opening
 * hours (simple same-day window).
 */
final class PlaceService
{
    public function nearby(
        float $lat,
        float $lng,
        int $radiusMeters,
        ?string $category,
        bool $openNow,
        int $perPage,
    ): LengthAwarePaginator {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';
        $nowTime = now()->format('H:i:s');

        return Place::query()
            ->selectRaw("places.*, ST_Distance(location, {$point}) AS distance_m", [$lng, $lat])
            ->when($category !== null, fn ($q) => $q->where('category', $category))
            ->when($openNow, fn ($q) => $q->where(function ($w) use ($nowTime): void {
                $w->where('is_24_7', true)
                    ->orWhereRaw('opens_at IS NOT NULL AND closes_at IS NOT NULL AND ? BETWEEN opens_at AND closes_at', [$nowTime]);
            }))
            ->whereRaw("ST_DWithin(location, {$point}, ?)", [$lng, $lat, $radiusMeters])
            ->orderBy('distance_m')
            ->paginate($perPage);
    }
}
