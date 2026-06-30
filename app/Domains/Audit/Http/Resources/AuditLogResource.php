<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Resources;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'organization_id' => $this->organization_id,
            'actor_id' => $this->user_id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'subject' => $this->auditable_type === null ? null : [
                'type' => class_basename($this->auditable_type),
                'id' => $this->auditable_id,
            ],
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
