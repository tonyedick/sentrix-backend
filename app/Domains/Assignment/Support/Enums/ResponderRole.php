<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Support\Enums;

/**
 * A responder's role within an assignment. Exactly one active primary is allowed
 * per assignment (enforced by a partial unique index); supporting may be many.
 */
enum ResponderRole: string
{
    case Primary = 'primary';
    case Supporting = 'supporting';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
