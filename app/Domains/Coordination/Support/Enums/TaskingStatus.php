<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Support\Enums;

/**
 * Tasking lifecycle (HQ routes -> assignee acknowledges -> resolved). Sender-
 * stamped. Mirrors the SentrixGoBackend command router's SENT->ACKNOWLEDGED->
 * RESOLVED tasking machine.
 */
enum TaskingStatus: string
{
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
