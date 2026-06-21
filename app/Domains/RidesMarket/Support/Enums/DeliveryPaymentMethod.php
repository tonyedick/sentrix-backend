<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Support\Enums;

/**
 * How a Sentrix Send delivery is paid: from the rider's wallet up-front, or
 * Cash-on-Delivery (the courier collects from the recipient — the NG staple).
 */
enum DeliveryPaymentMethod: string
{
    case Wallet = 'wallet';
    case Cod = 'cod';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
