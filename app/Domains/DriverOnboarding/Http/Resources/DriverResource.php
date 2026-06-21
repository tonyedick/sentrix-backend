<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Resources;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
final class DriverResource extends JsonResource
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
            'reviewer_id' => $this->reviewer_id,
            'review_note' => $this->review_note,
            'fleet_safety_score' => $this->fleet_safety_score,
            'trips_count' => $this->trips_count,
            // A whole-number decimal serialises without a trailing .0; tests assert
            // the int/string form. Expose the cast value (decimal:2 string) as-is.
            'rating_avg' => $this->rating_avg,
            'vehicle' => [
                'make' => $this->vehicle_make,
                'model' => $this->vehicle_model,
                'plate' => $this->vehicle_plate,
                'color' => $this->vehicle_color,
            ],
            'installed_hardware' => $this->installed_hardware,
            'documents' => DriverDocumentResource::collection($this->whenLoaded('documents')),
            'latest_inspection' => $this->when(
                $this->relationLoaded('inspections'),
                fn () => InspectionResource::make($this->inspections->first()),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
