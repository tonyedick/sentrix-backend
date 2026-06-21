<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Support\Enums;

enum TransactionDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
