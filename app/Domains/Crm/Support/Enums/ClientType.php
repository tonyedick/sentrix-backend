<?php

declare(strict_types=1);

namespace App\Domains\Crm\Support\Enums;

enum ClientType: string
{
    case University = 'university';
    case Estate = 'estate';
    case Company = 'company';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
