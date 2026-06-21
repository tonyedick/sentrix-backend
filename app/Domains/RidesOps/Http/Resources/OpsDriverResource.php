<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Http\Resources;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops projection of a DriverOnboarding Driver for the unified fleet roster:
 * stage + availability + vehicle snapshot.
 *
 * @mixin Driver
 */
final class OpsDriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'stage' => $this->stage->value,
            'availability' => $this->availability->value,
            'fleet_safety_score' => $this->fleet_safety_score,
            'trips_count' => $this->trips_count,
            'rating_avg' => $this->rating_avg,
            'vehicle' => [
                'make' => $this->vehicle_make,
                'model' => $this->vehicle_model,
                'plate' => $this->vehicle_plate,
                'color' => $this->vehicle_color,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
