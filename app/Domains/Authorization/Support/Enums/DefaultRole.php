<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support\Enums;

/**
 * The default roles provisioned for every new organization, together with the
 * permissions each one grants. The OrganizationService materialises these as
 * team-scoped Spatie roles when an organization is created.
 */
enum DefaultRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Billing = 'billing';

    /**
     * Permissions granted to this role. The Owner is handled via a Gate::before
     * super-grant within its organization, so it implicitly has everything.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => DefaultPermission::values(),
            self::Admin => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::OrganizationUpdate->value,
                DefaultPermission::MembersView->value,
                DefaultPermission::MembersInvite->value,
                DefaultPermission::MembersUpdate->value,
                DefaultPermission::MembersRemove->value,
                DefaultPermission::RolesManage->value,
            ],
            self::Member => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::MembersView->value,
            ],
            self::Billing => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::BillingManage->value,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
