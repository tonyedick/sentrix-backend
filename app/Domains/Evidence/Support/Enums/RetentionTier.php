<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Support\Enums;

/**
 * Storage tier an observation currently lives in. New captures land in `hot`;
 * the (separate) Retention domain will age them through warm/cold/archived.
 */
enum RetentionTier: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
