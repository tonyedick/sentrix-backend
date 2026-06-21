<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Support\Enums;

/**
 * Lifecycle state of an insurance policy.
 *
 *   draft     → created, not yet in force.
 *   active    → in force.
 *   lapsed    → coverage ended (e.g. period elapsed or non-payment).
 *   cancelled → terminated (terminal).
 */
enum PolicyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Lapsed = 'lapsed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
