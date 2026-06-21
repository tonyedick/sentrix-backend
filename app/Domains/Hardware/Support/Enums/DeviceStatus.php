<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Support\Enums;

/**
 * Operational state of a registered hardware device.
 *
 *  - active:      registered and expected to report in.
 *  - offline:     not reporting / unreachable.
 *  - maintenance: intentionally taken out of service.
 *  - retired:     permanently decommissioned (terminal).
 */
enum DeviceStatus: string
{
    case Active = 'active';
    case Offline = 'offline';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
