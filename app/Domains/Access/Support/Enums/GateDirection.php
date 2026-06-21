<?php

declare(strict_types=1);

namespace App\Domains\Access\Support\Enums;

enum GateDirection: string
{
    case In = 'in';
    case Out = 'out';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
