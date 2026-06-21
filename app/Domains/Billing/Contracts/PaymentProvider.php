<?php

declare(strict_types=1);

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\DTOs\PaymentResult;
use App\Models\User;

/**
 * Charges a user for a subscription. Real drivers integrate a processor
 * (Paystack / Flutterwave / Stripe); the 'log' stub approves without external
 * calls so the rest of the billing flow is testable.
 */
interface PaymentProvider
{
    public function charge(User $user, int $amountCents, string $currency, string $reference): PaymentResult;

    public function name(): string;
}
