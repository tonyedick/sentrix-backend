<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Support\Enums;

/**
 * Investigative priority of an observation. Audio/thermal events are typically
 * critical; routine scene frames are info.
 */
enum ObservationSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
