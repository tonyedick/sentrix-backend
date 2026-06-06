<?php

declare(strict_types=1);

namespace App\Domains\Trip\Support\Enums;

/**
 * Lifecycle states for a monitored journey.
 *
 *   active    → in progress
 *   overdue   → still active but past its expected arrival (escalation trigger)
 *   completed → arrived safely (terminal)
 *   cancelled → called off (terminal)
 */
enum TripStatus: string
{
    case Active = 'active';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Terminal states accept no further transitions.
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
