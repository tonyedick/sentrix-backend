<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Resources;

use App\Domains\Trip\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trip
 */
final class TripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'status' => $this->status->value,
            'origin' => [
                'label' => $this->origin_label,
                'lat' => $this->origin_lat,
                'lng' => $this->origin_lng,
            ],
            'destination' => [
                'label' => $this->destination_label,
                'lat' => $this->destination_lat,
                'lng' => $this->destination_lng,
            ],
            'started_at' => $this->started_at?->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
