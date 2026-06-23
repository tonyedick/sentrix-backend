<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Resources;

use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Http\Resources\ResponderAssignmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Responder
 */
final class ResponderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'user_email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'status' => $this->status->value,
            'on_duty' => $this->on_duty,
            'assignable' => $this->status->isAssignable(),
            'last_lat' => $this->last_lat !== null ? (float) $this->last_lat : null,
            'last_lng' => $this->last_lng !== null ? (float) $this->last_lng : null,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'current_assignment_id' => $this->current_assignment_id,
            'current_assignment' => ResponderAssignmentResource::make($this->whenLoaded('currentAssignment')),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
