<?php

declare(strict_types=1);

namespace App\Domains\Command\Support\Enums;

/**
 * Onboarding state of a responder agency. Only active agencies lead routing.
 */
enum AgencyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
