<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Resources;

use App\Domains\Organization\Http\Resources\OrganizationResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\PermissionRegistrar;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
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
            'phone' => $this->phone,
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
            'preferences' => $this->preferences ?? (object) [],
            'current_organization_id' => $this->current_organization_id,
            'current_organization' => OrganizationResource::make($this->whenLoaded('currentOrganization')),
            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations')),
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'permissions' => $this->when(
                $request->boolean('with_permissions'),
                fn () => $this->getAllPermissions()->pluck('name'),
            ),
            // The organization the roles/permissions above were resolved for
            // (null = global scope). Lets clients cache identity per org.
            'roles_organization_id' => $this->resolvedTeamId(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The Spatie team (organization) id the role/permission checks resolved
     * against for this request, if any.
     */
    private function resolvedTeamId(): ?string
    {
        $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        return $teamId === null ? null : (string) $teamId;
    }
}
