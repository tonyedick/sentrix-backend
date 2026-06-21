<?php

declare(strict_types=1);

namespace App\Domains\Community\Support\Enums;

enum AlertImpact: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
