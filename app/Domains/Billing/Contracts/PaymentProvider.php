<?php

declare(strict_types=1);

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\DTOs\PaymentResult;
use App\Domains\Billing\Models\Payment;
use App\Models\User;

/**
 * Charges a user for a subscription. Real drivers integrate a processor
 * (Paystack / Flutterwave / Stripe); the 'log' stub approves without external
 * calls so the rest of the billing flow is testable.
 */
interface PaymentProvider
{
    public function charge(User $user, int $amountCents, string $currency, string $reference): PaymentResult;

    /**
     * Hand a pending Payment off to the processor and return the checkout
     * descriptor the client redirects to.
     *
     * @return array{reference: string, checkout_url: string, amount_cents: int, currency: string, provider: string}
     */
    public function createCheckout(Payment $payment): array;

    /**
     * Verify a webhook delivery: constant-time compare the signature header to
     * an HMAC over the RAW request payload using the configured webhook secret.
     */
    public function verifyWebhook(string $payload, string $signature): bool;

    public function name(): string;
}
