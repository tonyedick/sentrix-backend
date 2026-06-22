<?php

declare(strict_types=1);

namespace App\Domains\Billing\Support;

use App\Domains\Billing\Contracts\PaymentProvider;
use App\Domains\Billing\DTOs\PaymentResult;
use App\Domains\Billing\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * No-op payment provider for dev/test: logs the intended charge and approves it.
 * Swap for a real processor in production via config('sentrix.billing.payment_driver').
 *
 * For PSP checkout it returns a deterministic sandbox checkout_url keyed off the
 * payment's reference, and verifies webhooks by constant-time comparing an
 * HMAC-SHA256 of the RAW payload (config sentrix.billing.webhook_secret) to the
 * presented signature. With no secret configured, signature verification only
 * succeeds in local/testing (closed by default in production).
 */
final class LogPaymentProvider implements PaymentProvider
{
    public function charge(User $user, int $amountCents, string $currency, string $reference): PaymentResult
    {
        Log::info('billing.charge.stub', [
            'user_id' => $user->getKey(),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'reference' => $reference,
        ]);

        return new PaymentResult(
            successful: true,
            reference: $reference,
            paymentMethodLabel: 'stub-card',
        );
    }

    /**
     * @return array{reference: string, checkout_url: string, amount_cents: int, currency: string, provider: string}
     */
    public function createCheckout(Payment $payment): array
    {
        Log::info('billing.checkout.stub', [
            'user_id' => $payment->user_id,
            'reference' => $payment->reference,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
        ]);

        return [
            'reference' => $payment->reference,
            'checkout_url' => '/billing/sandbox/'.$payment->reference,
            'amount_cents' => (int) $payment->amount_cents,
            'currency' => $payment->currency,
            'provider' => $this->name(),
        ];
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = config('sentrix.billing.webhook_secret');
        $secret = is_string($secret) && $secret !== '' ? $secret : null;

        if ($secret === null) {
            // No secret set: accept only in dev/test, closed by default otherwise.
            return app()->environment('local', 'testing');
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function name(): string
    {
        return 'log';
    }
}
