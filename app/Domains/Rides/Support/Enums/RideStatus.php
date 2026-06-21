<?php

declare(strict_types=1);

namespace App\Domains\Rides\Support\Enums;

enum RideStatus: string
{
    case Requested = 'requested';
    case Matched = 'matched';
    case Arriving = 'arriving';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * A ride may still be cancelled while it is pre-trip.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Requested, self::Matched, self::Arriving], true);
    }

    /**
     * A ride may be completed (paid out) once a driver is matched and up to the
     * point the trip is in progress. (We accept matched/arriving/in_progress so
     * the demo flow — match then complete — works without a live dispatch loop.)
     */
    public function isCompletable(): bool
    {
        return in_array($this, [self::Matched, self::Arriving, self::InProgress], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
