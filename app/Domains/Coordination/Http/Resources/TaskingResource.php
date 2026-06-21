<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Resources;

use App\Domains\Coordination\Models\Tasking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tasking
 */
final class TaskingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'ref' => $this->ref,
            'title' => $this->title,
            'assignee' => $this->assignee,
            'status' => $this->status->value,
            'created_by' => $this->created_by,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
