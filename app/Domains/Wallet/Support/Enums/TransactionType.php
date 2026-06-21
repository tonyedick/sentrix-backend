<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Support\Enums;

enum TransactionType: string
{
    case Topup = 'topup';
    case Charge = 'charge';
    case Payout = 'payout';
    case ReferralCredit = 'referral_credit';
    case Refund = 'refund';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
