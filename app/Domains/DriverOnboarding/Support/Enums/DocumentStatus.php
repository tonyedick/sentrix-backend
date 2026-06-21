<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Support\Enums;

/**
 * Staff review state of a single uploaded driver document.
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
