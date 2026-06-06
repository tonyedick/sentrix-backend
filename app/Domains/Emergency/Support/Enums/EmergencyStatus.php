<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Support\Enums;

/**
 * Emergency lifecycle.
 *
 *   triggered    → raised, awaiting acknowledgement
 *   acknowledged → a responder/dispatcher has taken ownership
 *   resolved     → handled (terminal)
 *   cancelled    → false alarm / stood down (terminal)
 */
enum EmergencyStatus: string
{
    case Triggered = 'triggered';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Resolved || $this === self::Cancelled;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
