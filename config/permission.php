<?php

return [

    'models' => [
        /*
         * Custom UUID-keyed models living in the Authorization domain.
         */
        'permission' => App\Domains\Authorization\Models\Permission::class,
        'role' => App\Domains\Authorization\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,        // defaults to role_id
        'permission_pivot_key' => null,  // defaults to permission_id

        /*
         * The morph key for the model_has_* tables. Users use UUIDs so the
         * migration types this column as uuid.
         */
        'model_morph_key' => 'model_id',

        /*
         * Team scoping: roles/permissions are scoped per organization.
         */
        'team_foreign_key' => 'organization_id',
    ],

    /*
     * Multi-tenant, organization-scoped roles & permissions.
     */
    'teams' => true,

    /*
     * Resolve the active team (organization) id from the authenticated user.
     * The SetOrganizationTeam middleware sets it explicitly per request, so a
     * resolver is only a fallback for non-HTTP contexts (queued jobs, console).
     */
    'team_resolver' => Spatie\Permission\DefaultTeamResolver::class,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
