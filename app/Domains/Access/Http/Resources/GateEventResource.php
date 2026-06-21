<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Resources;

use App\Domains\Access\Models\GateEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GateEvent
 */
final class GateEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'pass_id' => $this->pass_id,
            'officer_id' => $this->officer_id,
            'gate' => $this->gate,
            'direction' => $this->direction->value,
            'result' => $this->result->value,
            'reason' => $this->reason,
            'visitor_name' => $this->visitor_name,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
