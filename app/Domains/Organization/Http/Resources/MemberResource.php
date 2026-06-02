<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Represents a user in the context of an organization (with their org-scoped
 * roles). Requires the organization team context to be active on the request.
 *
 * @mixin User
 */
final class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames(),
            'joined_at' => $this->whenPivotLoaded('organization_user', fn () => $this->pivot->joined_at?->toIso8601String()),
        ];
    }
}
