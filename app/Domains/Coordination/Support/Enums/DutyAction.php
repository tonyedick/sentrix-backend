<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Support\Enums;

/**
 * A duty-book action. Append-only sign-in book per control room. Mirrors the
 * SentrixGoBackend command router duty payload (sign_in|sign_out|handover).
 */
enum DutyAction: string
{
    case SignIn = 'sign_in';
    case SignOut = 'sign_out';
    case Handover = 'handover';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
