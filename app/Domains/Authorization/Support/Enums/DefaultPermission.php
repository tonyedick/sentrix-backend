<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support\Enums;

/**
 * The global permission catalogue. Permissions are guard-global definitions;
 * roles (which are organization-scoped) are what bundle them per tenant.
 */
enum DefaultPermission: string
{
    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';
    case OrganizationDelete = 'organization.delete';

    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersUpdate = 'members.update';
    case MembersRemove = 'members.remove';

    case RolesManage = 'roles.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
