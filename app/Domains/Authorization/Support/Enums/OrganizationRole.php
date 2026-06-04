<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support\Enums;

/**
 * The default organization-scoped roles provisioned for every new organization,
 * together with the permissions each one grants. RoleService materialises these
 * as team-scoped Spatie roles when an organization is created.
 *
 * Platform-global roles (e.g. SuperAdmin) live in {@see SystemRole}.
 */
enum OrganizationRole: string
{
    case OrganizationAdmin = 'OrganizationAdmin';
    case Dispatcher = 'Dispatcher';
    case Responder = 'Responder';
    case User = 'User';

    /**
     * Permissions granted to this role within its organization.
     *
     * The organization owner additionally receives an implicit super-grant
     * within their own organization (see AuthorizationServiceProvider), so the
     * OrganizationAdmin set intentionally excludes destructive org-level
     * abilities such as deleting the organization.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OrganizationAdmin => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::OrganizationUpdate->value,
                DefaultPermission::MembersView->value,
                DefaultPermission::MembersInvite->value,
                DefaultPermission::MembersUpdate->value,
                DefaultPermission::MembersRemove->value,
                DefaultPermission::RolesManage->value,
            ],
            self::Dispatcher => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::MembersView->value,
            ],
            self::Responder => [
                DefaultPermission::OrganizationView->value,
            ],
            self::User => [
                DefaultPermission::OrganizationView->value,
            ],
        };
    }

    /**
     * The role assigned to the user who creates an organization.
     */
    public static function owner(): self
    {
        return self::OrganizationAdmin;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
