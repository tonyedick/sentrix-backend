<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Resources;

use App\Domains\DriverOnboarding\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inspection
 */
final class InspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'vetting_center_id' => $this->vetting_center_id,
            'booked_slot' => $this->booked_slot,
            'status' => $this->status->value,
            'checklist' => $this->checklist,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
