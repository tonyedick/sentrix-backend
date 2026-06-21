<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Resources;

use App\Domains\Cad\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
final class UnitResource extends JsonResource
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
            'call_sign' => $this->call_sign,
            'kind' => $this->kind->value,
            'capabilities' => $this->capabilities ?? [],
            'crew' => $this->crew,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'area' => $this->area,
            'status' => $this->status->value,
            'assigned_incident_id' => $this->assigned_incident_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
