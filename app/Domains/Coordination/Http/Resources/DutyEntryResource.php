<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Resources;

use App\Domains\Coordination\Models\DutyEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DutyEntry
 */
final class DutyEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_type' => $this->scope_type->value,
            'scope_id' => $this->scope_id,
            'person_name' => $this->person_name,
            'role' => $this->role,
            'action' => $this->action->value,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
