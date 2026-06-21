<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Resources;

use App\Domains\Assignment\Models\AssignmentResponder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A responder's participation in an assignment, from the responder's point of
 * view: the line lifecycle plus a compact summary of the incident it belongs to.
 * Powers the responder workspace's current-assignment + history panels.
 *
 * @mixin AssignmentResponder
 */
final class ResponderAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'incident_id' => $this->incident_id,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'attempt' => $this->attempt,
            'offered_at' => $this->offered_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'en_route_at' => $this->en_route_at?->toIso8601String(),
            'on_scene_at' => $this->on_scene_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
            'outcome' => $this->outcome,
            'created_at' => $this->created_at?->toIso8601String(),
            'incident' => $this->whenLoaded('incident', fn (): ?array => $this->incident === null ? null : [
                'id' => $this->incident->id,
                'title' => $this->incident->title,
                'status' => $this->incident->status->value,
                'severity' => $this->incident->severity->value,
            ]),
        ];
    }
}
