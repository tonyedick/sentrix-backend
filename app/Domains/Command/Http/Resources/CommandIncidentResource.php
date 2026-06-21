<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Resources;

use App\Domains\Command\Models\CommandIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommandIncident
 */
final class CommandIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'command_id' => $this->command_id,
            'agency_id' => $this->agency_id,
            'category' => $this->category->value,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'source_type' => $this->source_type->value,
            'source_ref' => $this->source_ref,
            'summary' => $this->summary,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'sla_dispatch_due_at' => $this->sla_dispatch_due_at?->toIso8601String(),
            'sla_onscene_due_at' => $this->sla_onscene_due_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
