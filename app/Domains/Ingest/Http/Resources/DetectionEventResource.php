<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Resources;

use App\Domains\Ingest\Models\DetectionEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DetectionEvent
 */
final class DetectionEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'source' => $this->source->value,
            'product' => $this->product,
            'camera_source_id' => $this->camera_source_id,
            'type' => $this->type,
            'severity' => $this->severity->value,
            'risk_score' => (int) $this->risk_score,
            'triggered' => (bool) $this->triggered,
            'incident_id' => $this->incident_id,
            'site' => $this->site,
            'zone' => $this->zone,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'summary' => $this->summary,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
