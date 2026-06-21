<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Support\Enums;

enum PaymentMethodKind: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Wallet = 'wallet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Cash and wallet are system methods that the user cannot remove.
     */
    public function isRemovable(): bool
    {
        return $this === self::Card;
    }
}
