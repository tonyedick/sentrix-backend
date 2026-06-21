<?php

declare(strict_types=1);

namespace App\Domains\Realtime\Support;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Support\Enums\ResponderStatus;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Central authorization for realtime channels. Every channel callback in
 * routes/channels.php delegates here so the rules live in one tested place.
 *
 * Isolation contract (in order):
 *   1. SuperAdmin (platform-global) is allowed everywhere.
 *   2. Otherwise the user MUST belong to the channel's organization — this is the
 *      cross-organization isolation gate.
 *   3. Then the channel's permission is checked in THAT organization's team
 *      context, so multi-organization users are authorized correctly per org.
 *
 * Channels are permission-scoped (not role-name-scoped), so a future Supervisor
 * role works automatically once granted the relevant view permissions.
 */
final readonly class RealtimeChannelPolicy
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /** Coordination dashboard (dispatchers/supervisors/admins). */
    public function dashboard(User $user, string $organizationId): bool
    {
        return $this->authorize($user, $organizationId, DefaultPermission::AssignmentsView->value);
    }

    /** Incident monitoring feed. */
    public function incidents(User $user, string $organizationId): bool
    {
        return $this->authorize($user, $organizationId, DefaultPermission::IncidentsView->value);
    }

    /** Assignment / dispatch update feed. */
    public function assignments(User $user, string $organizationId): bool
    {
        return $this->authorize($user, $organizationId, DefaultPermission::AssignmentsView->value);
    }

    /**
     * Presence roster of on-duty responders. On-duty responders join as
     * `responder`; users with responders.view join as `observer` (they see the
     * roster without appearing as responders). Anyone else is denied.
     *
     * @return array{id: string, name: string, type: string, responder_id: string|null, status: string|null}|false
     */
    public function responderPresence(User $user, string $organizationId): array|bool
    {
        if (! $this->memberOrSuperAdmin($user, $organizationId)) {
            return false;
        }

        $this->forCurrentTeam($user, $organizationId);

        $responder = Responder::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->getKey())
            ->where('on_duty', true)
            ->first();

        $isObserver = $user->isSuperAdmin() || $user->can(DefaultPermission::RespondersView->value);

        if ($responder === null && ! $isObserver) {
            return false;
        }

        return [
            'id' => (string) $user->getKey(),
            'name' => (string) $user->name,
            'type' => $responder !== null ? 'responder' : 'observer',
            'responder_id' => $responder?->getKey(),
            'status' => $responder?->status instanceof ResponderStatus ? $responder->status->value : null,
        ];
    }

    private function authorize(User $user, string $organizationId, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->belongsToOrganization($organizationId)) {
            return false;
        }

        $this->forCurrentTeam($user, $organizationId);

        return $user->can($permission);
    }

    private function memberOrSuperAdmin(User $user, string $organizationId): bool
    {
        return $user->isSuperAdmin() || $user->belongsToOrganization($organizationId);
    }

    /**
     * Switch the Spatie team context AND drop the user's cached role/permission
     * relations, so a permission check re-evaluates for THIS organization. Without
     * the relation reset, a user checked across multiple organizations in one
     * request would be evaluated against the first org's cached roles.
     */
    private function forCurrentTeam(User $user, string $organizationId): void
    {
        $this->registrar->setPermissionsTeamId($organizationId);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
