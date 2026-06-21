<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Resources;

use App\Domains\Coordination\Models\MutualAidRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MutualAidRequest
 */
final class MutualAidRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'command_incident_id' => $this->command_incident_id,
            'requesting_command_id' => $this->requesting_command_id,
            'responding_command_id' => $this->responding_command_id,
            'status' => $this->status->value,
            'message' => $this->message,
            'requested_by' => $this->requested_by,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
