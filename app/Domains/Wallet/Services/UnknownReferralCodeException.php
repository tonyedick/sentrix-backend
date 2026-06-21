<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Services;

use RuntimeException;

/**
 * Raised when a referral code does not resolve to any user. The controller maps
 * this to HTTP 404 Not Found.
 */
final class UnknownReferralCodeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Unknown referral code.');
    }
}
