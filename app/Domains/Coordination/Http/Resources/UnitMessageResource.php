<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Resources;

use App\Domains\Coordination\Models\UnitMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UnitMessage
 */
final class UnitMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'command_incident_id' => $this->command_incident_id,
            'direction' => $this->direction->value,
            'body' => $this->body,
            'sender' => $this->sender,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
