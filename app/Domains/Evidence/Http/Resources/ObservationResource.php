<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Http\Resources;

use App\Domains\Evidence\Models\Observation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Observation
 */
final class ObservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'camera_source_id' => $this->camera_source_id,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'attributes' => $this->attributes,
            'plate' => $this->plate,
            'confidence' => $this->confidence !== null ? (float) $this->confidence : null,
            'severity' => $this->severity?->value,
            'snapshot_url' => $this->snapshot_url,
            'clip_url' => $this->clip_url,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'observed_at' => $this->observed_at?->toIso8601String(),
            'hold' => $this->hold,
            'bookmarked' => $this->bookmarked,
            'sealed' => $this->sealed,
            'retention_tier' => $this->retention_tier->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
