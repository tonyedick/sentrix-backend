<?php

declare(strict_types=1);

namespace App\Domains\Command\Support\Enums;

/**
 * The 4-tier command hierarchy: national -> state -> zonal -> divisional.
 * `depth()` orders tiers so routing can prefer the MOST SPECIFIC (deepest)
 * command near a point.
 */
enum CommandTier: string
{
    case National = 'national';
    case State = 'state';
    case Zonal = 'zonal';
    case Divisional = 'divisional';

    /**
     * Depth in the tree (national = 0, divisional = 3). Higher is more specific.
     */
    public function depth(): int
    {
        return match ($this) {
            self::National => 0,
            self::State => 1,
            self::Zonal => 2,
            self::Divisional => 3,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
