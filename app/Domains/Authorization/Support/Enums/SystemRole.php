<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support\Enums;

/**
 * Platform-global roles. These are stored as Spatie roles with a NULL team
 * (organization) id, so they transcend organization scope.
 *
 * SuperAdmin is granted every ability platform-wide via the Gate::before hook
 * registered in AuthorizationServiceProvider.
 */
enum SystemRole: string
{
    case SuperAdmin = 'SuperAdmin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
