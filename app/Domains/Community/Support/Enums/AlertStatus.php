<?php

declare(strict_types=1);

namespace App\Domains\Community\Support\Enums;

enum AlertStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
