<?php

declare(strict_types=1);

namespace App\Domains\Incident\Http\Resources;

use App\Domains\Incident\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Incident
 */
final class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'emergency_id' => $this->emergency_id,
            'opened_by' => $this->opened_by,
            'assigned_to' => $this->assigned_to,
            'status' => $this->status->value,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
