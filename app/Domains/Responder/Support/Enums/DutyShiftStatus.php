<?php

declare(strict_types=1);

namespace App\Domains\Responder\Support\Enums;

/**
 * Lifecycle of a scheduled duty window.
 *
 *   scheduled → planned, not yet started
 *   active    → currently in effect (responder on duty)
 *   completed → ended normally
 *   cancelled → called off before/while active
 */
enum DutyShiftStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
