<?php

declare(strict_types=1);

namespace App\Domains\Crm\Support\Enums;

enum LeadStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Quoted = 'quoted';
    case Won = 'won';
    case Lost = 'lost';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
