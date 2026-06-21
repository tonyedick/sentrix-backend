<?php

declare(strict_types=1);

namespace App\Domains\Cad\Support\Enums;

/**
 * The kind of BOLO / all-points / officer-safety broadcast.
 */
enum BoloKind: string
{
    case Vehicle = 'vehicle';
    case Person = 'person';
    case OfficerSafety = 'officer_safety';
    case General = 'general';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
