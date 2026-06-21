<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Support\Enums;

/**
 * Parcel size → fare multiplier on the equivalent passenger (go_safe) fare.
 * Mirrors SentrixGo rides_send.SIZES: small 0.85 / medium 1.05 / large 1.4.
 */
enum ParcelSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    /**
     * Multiplier applied to the base ride fare for this parcel size.
     */
    public function fareMultiplier(): float
    {
        return match ($this) {
            self::Small => 0.85,
            self::Medium => 1.05,
            self::Large => 1.4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small · fits a glovebox',
            self::Medium => 'Medium · a backpack',
            self::Large => 'Large · a full boot',
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
