<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Http\Resources;

use App\Domains\Assignment\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assignment
 */
final class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'incident_id' => $this->incident_id,
            'status' => $this->status->value,
            'dispatch_mode' => $this->dispatch_mode,
            'required_primary' => $this->required_primary,
            'required_supporting' => $this->required_supporting,
            'primary_assignment_responder_id' => $this->primary_assignment_responder_id,
            'escalation_level' => $this->escalation_level,
            'acceptance_deadline_at' => $this->acceptance_deadline_at?->toIso8601String(),
            'opened_by' => $this->opened_by,
            'responders' => AssignmentResponderResource::collection($this->whenLoaded('responders')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
