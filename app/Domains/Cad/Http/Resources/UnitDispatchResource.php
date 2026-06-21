<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Resources;

use App\Domains\Cad\Models\UnitDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UnitDispatch
 */
final class UnitDispatchResource extends JsonResource
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
            'dispatched_by' => $this->dispatched_by,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'cleared_at' => $this->cleared_at?->toIso8601String(),
            'outcome' => $this->outcome,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
