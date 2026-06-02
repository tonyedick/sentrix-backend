<?php

declare(strict_types=1);

namespace App\Domains\Organization\Services;

use App\Domains\Organization\Events\MemberJoined;
use App\Domains\Organization\Events\MemberRemoved;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owns the user <-> organization relationship and the user's org-scoped roles.
 * Always sets the Spatie team context before touching role assignments.
 */
final readonly class MembershipService
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function addMember(Organization $organization, User $user, string $role): OrganizationMembership
    {
        return DB::transaction(function () use ($organization, $user, $role): OrganizationMembership {
            /** @var OrganizationMembership $membership */
            $membership = OrganizationMembership::firstOrCreate(
                ['organization_id' => $organization->getKey(), 'user_id' => $user->getKey()],
                ['joined_at' => now()],
            );

            $this->registrar->setPermissionsTeamId($organization->getKey());
            $user->syncRoles([$role]);

            // First organization the user joins becomes their active context.
            if ($user->current_organization_id === null) {
                $user->forceFill(['current_organization_id' => $organization->getKey()])->save();
            }

            event(new MemberJoined($organization, $user, $role));

            return $membership;
        });
    }

    public function updateRole(Organization $organization, User $user, string $role): void
    {
        $this->registrar->setPermissionsTeamId($organization->getKey());
        $user->syncRoles([$role]);
    }

    public function removeMember(Organization $organization, User $user): void
    {
        if ($organization->owner_id === $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => ['The organization owner cannot be removed.'],
            ]);
        }

        DB::transaction(function () use ($organization, $user): void {
            $this->registrar->setPermissionsTeamId($organization->getKey());

            // Strip the user's roles within this organization only.
            foreach ($user->roles()->get() as $role) {
                $user->removeRole($role);
            }

            OrganizationMembership::query()
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $user->getKey())
                ->delete();

            if ($user->current_organization_id === $organization->getKey()) {
                $next = $user->organizations()
                    ->whereKeyNot($organization->getKey())
                    ->value('organizations.id');

                $user->forceFill(['current_organization_id' => $next])->save();
            }

            event(new MemberRemoved($organization, $user));
        });
    }
}
