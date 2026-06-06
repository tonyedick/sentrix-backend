<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Http\Resources;

use App\Domains\Trip\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip's current last-known position — the live-map snapshot row.
 *
 * @mixin Trip
 */
final class TripPositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'trip_id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status->value,
            'lat' => $this->last_lat,
            'lng' => $this->last_lng,
            'recorded_at' => $this->last_location_at?->toIso8601String(),
        ];
    }
}
