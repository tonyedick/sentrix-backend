<?php

declare(strict_types=1);

namespace App\Domains\Rides\Support\Enums;

enum RideClass: string
{
    case GoSafe = 'go_safe';
    case Comfort = 'comfort';
    case GoXl = 'go_xl';

    /**
     * Fare multiplier applied to the go_safe base fare.
     */
    public function fareMultiplier(): float
    {
        return match ($this) {
            self::GoSafe => 1.0,
            self::Comfort => 1.4,
            self::GoXl => 1.8,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GoSafe => 'Go Safe',
            self::Comfort => 'Comfort',
            self::GoXl => 'Go XL',
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
