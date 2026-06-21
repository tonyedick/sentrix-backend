<?php

declare(strict_types=1);

namespace App\Domains\Retention\Support\Enums;

/**
 * Serialization format of a retention archive export manifest. JSON is the only
 * format today; the enum exists so the column is pinned and future formats
 * (e.g. CSV, ZIP) slot in without a string sprinkled across the codebase.
 */
enum ExportFormat: string
{
    case Json = 'json';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
