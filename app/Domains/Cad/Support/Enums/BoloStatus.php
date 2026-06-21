<?php

declare(strict_types=1);

namespace App\Domains\Cad\Support\Enums;

/**
 * Lifecycle of a BOLO broadcast. cleared is terminal.
 */
enum BoloStatus: string
{
    case Active = 'active';
    case Cleared = 'cleared';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
