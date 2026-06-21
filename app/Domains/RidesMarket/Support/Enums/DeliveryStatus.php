<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Support\Enums;

enum DeliveryStatus: string
{
    case Requested = 'requested';
    case Matched = 'matched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
