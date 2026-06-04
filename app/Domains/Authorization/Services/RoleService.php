<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Authorization\Support\Enums\SystemRole;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * All role/permission mutations funnel through here so the team (organization)
 * context is always set before Spatie reads or writes.
 */
final readonly class RoleService
{
    private const string GUARD = 'web';

    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Ensure the global permission catalogue exists. Idempotent.
     */
    public function syncPermissionCatalogue(): void
    {
        $permission = app(PermissionRegistrar::class)->getPermissionClass();

        foreach (DefaultPermission::values() as $name) {
            $permission::findOrCreate($name, self::GUARD);
        }

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Ensure the platform-global SuperAdmin role exists (team id NULL).
     * Idempotent. Run from the permission catalogue seeder.
     */
    public function ensureSystemRoles(): void
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(null);

        foreach (SystemRole::values() as $name) {
            Role::findOrCreate($name, self::GUARD);
        }

        $this->registrar->setPermissionsTeamId($previous);
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Grant a user the platform-global SuperAdmin role (assigned with a NULL
     * team id so it transcends every organization).
     */
    public function assignSuperAdmin(User $user): void
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(null);

        $user->assignRole(SystemRole::SuperAdmin->value);

        $this->registrar->setPermissionsTeamId($previous);
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Create the standard role set for a freshly created organization.
     */
    public function provisionDefaultRoles(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $this->registrar->setPermissionsTeamId($organization->getKey());

            foreach (OrganizationRole::cases() as $default) {
                $role = Role::findOrCreate($default->value, self::GUARD);
                $role->syncPermissions($default->permissions());
            }

            $this->registrar->forgetCachedPermissions();
        });
    }

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function listForOrganization(Organization $organization, int $perPage): LengthAwarePaginator
    {
        $this->registrar->setPermissionsTeamId($organization->getKey());

        return Role::with('permissions')->paginate($perPage);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function create(Organization $organization, string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($organization, $name, $permissions): Role {
            $this->registrar->setPermissionsTeamId($organization->getKey());

            $role = Role::findOrCreate($name, self::GUARD);
            $role->syncPermissions($permissions);

            $this->registrar->forgetCachedPermissions();

            return $role->load('permissions');
        });
    }

    /**
     * @param  list<string>|null  $permissions
     */
    public function update(Role $role, ?string $name, ?array $permissions): Role
    {
        return DB::transaction(function () use ($role, $name, $permissions): Role {
            if ($name !== null) {
                $role->update(['name' => $name]);
            }

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            $this->registrar->forgetCachedPermissions();

            return $role->load('permissions');
        });
    }

    public function delete(Role $role): void
    {
        $role->delete();
        $this->registrar->forgetCachedPermissions();
    }
}
