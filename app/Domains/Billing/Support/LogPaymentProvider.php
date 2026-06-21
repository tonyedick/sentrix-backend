<?php

declare(strict_types=1);

namespace App\Domains\Billing\Support;

use App\Domains\Billing\Contracts\PaymentProvider;
use App\Domains\Billing\DTOs\PaymentResult;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * No-op payment provider for dev/test: logs the intended charge and approves it.
 * Swap for a real processor in production via config('sentrix.billing.payment_driver').
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

    public function name(): string
    {
        return 'log';
    }
}
