<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Resources;

use App\Domains\Organization\Models\OrganizationInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationInvitation
 */
final class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->accepted_at !== null ? 'accepted' : ($this->isExpired() ? 'expired' : 'pending'),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }
}
