<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Resources;

use App\Domains\Responder\Models\ResponderCertification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResponderCertification
 */
final class ResponderCertificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'responder_id' => $this->responder_id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'authority' => $this->authority,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'status' => $this->status->value,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
