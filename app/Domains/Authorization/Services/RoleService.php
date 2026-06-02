<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Authorization\Support\Enums\DefaultRole;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
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
     * Create the standard role set for a freshly created organization.
     */
    public function provisionDefaultRoles(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $this->registrar->setPermissionsTeamId($organization->getKey());

            foreach (DefaultRole::cases() as $default) {
                $role = Role::findOrCreate($default->value, self::GUARD);
                $role->syncPermissions($default->permissions());
            }

            $this->registrar->forgetCachedPermissions();
        });
    }

    /**
     * @return Collection<int, Role>
     */
    public function listForOrganization(Organization $organization): Collection
    {
        $this->registrar->setPermissionsTeamId($organization->getKey());

        return Role::with('permissions')->get();
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
