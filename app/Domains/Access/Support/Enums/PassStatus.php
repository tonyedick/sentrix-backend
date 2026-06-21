<?php

declare(strict_types=1);

namespace App\Domains\Access\Support\Enums;

/**
 * Lifecycle state of a visitor pass.
 *
 *  - active:   usable (subject to its validity window).
 *  - consumed: a single-use pass that has been spent on entry.
 *  - revoked:  cancelled by the host or a manager (terminal).
 *
 * Expiry is derived from the validity window at scan time and is not stored as
 * a status, so a pass never needs a background job to "expire".
 */
enum PassStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
