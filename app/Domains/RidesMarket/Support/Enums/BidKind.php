<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Support\Enums;

enum BidKind: string
{
    case Accept = 'accept';
    case Counter = 'counter';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
