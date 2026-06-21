<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Support\Enums;

/**
 * Whether a (live) driver is accepting rides. Only an `active` driver may go
 * online — the safety boundary enforced by the DriverService.
 */
enum DriverAvailability: string
{
    case Offline = 'offline';
    case Online = 'online';
    case OnTrip = 'on_trip';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
