<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Models\Role;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents privilege escalation through the RBAC surface. The governing rule is
 * "you cannot give away more than you hold": an actor may only grant a
 * permission set (or assign a role) that is a subset of their own effective
 * permissions within the organization.
 *
 * Two principals bypass the check because they already hold every ability in the
 * organization: a platform SuperAdmin, and the organization's owner (whose
 * super-grant is applied in AuthorizationServiceProvider).
 *
 * Relies on the active Spatie team context (set by SetOrganizationTeam
 * middleware) so `getAllPermissions()` resolves within the right tenant.
 */
final class PermissionGuard
{
    /**
     * @param  list<string>  $permissionNames
     */
    public function actorMayGrant(User $actor, Organization $organization, array $permissionNames): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($organization->owner_id === $actor->getKey()) {
            return true;
        }

        $held = $actor->getAllPermissions()->pluck('name')->all();

        return array_diff(array_values($permissionNames), $held) === [];
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function assertMayGrant(User $actor, Organization $organization, array $permissionNames): void
    {
        abort_unless(
            $this->actorMayGrant($actor, $organization, $permissionNames),
            Response::HTTP_FORBIDDEN,
            'You cannot grant permissions beyond your own.',
        );
    }

    /**
     * A role may only be assigned by an actor who holds every permission the
     * role confers.
     */
    public function assertMayAssignRole(User $actor, Organization $organization, Role $role): void
    {
        abort_unless(
            $this->actorMayGrant($actor, $organization, $role->permissions()->pluck('name')->all()),
            Response::HTTP_FORBIDDEN,
            'You cannot assign a role more privileged than your own.',
        );
    }
}
