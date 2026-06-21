<?php

declare(strict_types=1);

namespace App\Domains\Access\Support\Enums;

enum GateResult: string
{
    case Granted = 'granted';
    case Denied = 'denied';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
