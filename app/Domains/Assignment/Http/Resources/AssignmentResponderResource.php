<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Http\Resources;

use App\Domains\Assignment\Models\AssignmentResponder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssignmentResponder
 */
final class AssignmentResponderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'organization_id' => $this->organization_id,
            'responder_id' => $this->responder_id,
            'incident_id' => $this->incident_id,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'attempt' => $this->attempt,
            'assigned_by' => $this->assigned_by,
            'offered_at' => $this->offered_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'en_route_at' => $this->en_route_at?->toIso8601String(),
            'on_scene_at' => $this->on_scene_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
            'outcome' => $this->outcome,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
