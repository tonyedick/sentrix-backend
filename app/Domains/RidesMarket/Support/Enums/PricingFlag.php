<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Support\Enums;

/**
 * How a rider's proposed fare compares to the system fair estimate:
 * low (<0.8x) | fair (0.8x-1.2x) | high (>1.2x).
 */
enum PricingFlag: string
{
    case Low = 'low';
    case Fair = 'fair';
    case High = 'high';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
