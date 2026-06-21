<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Support\Enums;

/**
 * Outcome of an in-person vehicle inspection + hardware install.
 */
enum InspectionStatus: string
{
    case Booked = 'booked';
    case Passed = 'passed';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
