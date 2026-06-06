<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Http\Resources;

use App\Domains\Tracking\Models\TripLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TripLocation
 */
final class TripLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'accuracy' => $this->accuracy,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}
