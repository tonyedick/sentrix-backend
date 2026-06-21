<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Support\Enums;

/**
 * The control-room scope a duty action is recorded against — a monitoring
 * centre, a client site, or a command. Mirrors the duty-book scope_type of the
 * SentrixGoBackend command router.
 */
enum DutyScopeType: string
{
    case Center = 'center';
    case Client = 'client';
    case Command = 'command';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
