<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Support\Enums;

/**
 * Lifecycle of an inter-agency mutual-aid request. Mirrors Omni's mutualaid.js
 * coordination flow (a command requests assistance from another agency).
 * accepted/declined/cancelled are terminal from `requested`.
 */
enum MutualAidStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
