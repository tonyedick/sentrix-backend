<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Resources;

use App\Domains\Access\Models\Pass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pass
 */
final class PassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'host_id' => $this->host_id,
            'code' => $this->code,
            'visitor_name' => $this->visitor_name,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'uses_count' => $this->uses_count,
            'revoked_by' => $this->revoked_by,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
