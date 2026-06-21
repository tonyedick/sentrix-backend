<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Services;

use RuntimeException;

/**
 * Raised by WalletService::charge when the wallet balance is below the requested
 * amount. The controller maps this to HTTP 402 Payment Required with the
 * shortfall so the app can prompt an inline top-up.
 */
final class InsufficientBalanceException extends RuntimeException
{
    public function __construct(public readonly int $shortfallCents)
    {
        parent::__construct('Insufficient wallet balance.');
    }
}
