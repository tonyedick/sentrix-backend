<?php

declare(strict_types=1);

namespace App\Domains\Places\Support\Enums;

enum PlaceCategory: string
{
    case Police = 'police';
    case Hospital = 'hospital';
    case FireService = 'fire_service';
    case Towing = 'towing';
    case Fuel = 'fuel';
    case Parking = 'parking';
    case Pharmacy = 'pharmacy';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
