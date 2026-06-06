<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Http\Resources;

use App\Domains\Emergency\Models\Emergency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Emergency
 */
final class EmergencyResource extends JsonResource
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
            'trip_id' => $this->trip_id,
            'status' => $this->status->value,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'location' => [
                'lat' => $this->lat,
                'lng' => $this->lng,
            ],
            'triggered_at' => $this->triggered_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $this->acknowledged_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolved_by,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
