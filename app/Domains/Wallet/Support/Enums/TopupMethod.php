<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Support\Enums;

/**
 * Local-rail top-up methods. (The wider transaction `method` column also allows
 * `wallet` and `system`, used by charges / referral credits respectively.)
 */
enum TopupMethod: string
{
    case Transfer = 'transfer';
    case Ussd = 'ussd';
    case Card = 'card';
    case Bank = 'bank';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
