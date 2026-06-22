<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\PaymentProvider;
use App\Domains\Billing\Models\Payment;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PSP checkout + multi-region catalog pricing. Prices the config plan book for a
 * region (currency + FX rate + a simple tax line), persists a PENDING Payment,
 * and hands it to the PaymentProvider for a checkout descriptor. All amounts are
 * INTEGER CENTS — never floats. User-scoped (ADR-0001).
 */
final readonly class CheckoutService
{
    public function __construct(
        private PaymentProvider $payments,
        private DatabaseManager $db,
    ) {}

    /**
     * Normalize a region code to a configured one, defaulting per config.
     */
    public function resolveRegion(?string $region): string
    {
        $regions = (array) config('sentrix.billing.regions', []);
        $code = Str::upper((string) $region);

        if ($code !== '' && array_key_exists($code, $regions)) {
            return $code;
        }

        return (string) config('sentrix.billing.default_region', 'NG');
    }

    /**
     * @return array<string, mixed>
     */
    public function regionConfig(string $region): array
    {
        /** @var array<string, mixed> $cfg */
        $cfg = config("sentrix.billing.regions.{$region}", []);

        return $cfg;
    }

    /**
     * Price one plan for a region. Returns integer-cents subtotal/tax/total and
     * the region currency. The base price book (price_cents) is localized via
     * the region `rate` (FX multiplier), then a `tax_rate` line is added.
     *
     * @param  array<string, mixed>  $plan
     * @return array{subtotal_cents: int, tax_cents: int, amount_cents: int, currency: string}
     */
    public function priceFor(array $plan, string $region): array
    {
        $cfg = $this->regionConfig($region);
        $rate = (float) ($cfg['rate'] ?? 1.0);
        $taxRate = (float) ($cfg['tax_rate'] ?? 0.0);
        $currency = (string) ($cfg['currency'] ?? config('sentrix.billing.currency', 'USD'));

        $base = (int) ($plan['price_cents'] ?? 0);
        $subtotal = (int) round($base * $rate);
        $tax = (int) round($subtotal * $taxRate);

        return [
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'amount_cents' => $subtotal + $tax,
            'currency' => $currency,
        ];
    }

    /**
     * The full plan catalog priced for a region.
     *
     * @return array<string, mixed>
     */
    public function catalogFor(string $region): array
    {
        $cfg = $this->regionConfig($region);
        $currency = (string) ($cfg['currency'] ?? config('sentrix.billing.currency', 'USD'));

        $plans = [];
        /** @var array<string, array<string, mixed>> $book */
        $book = config('sentrix.billing.plans', []);

        foreach ($book as $key => $plan) {
            $price = $this->priceFor($plan, $region);

            $plans[] = [
                'key' => $key,
                'name' => $plan['name'] ?? $key,
                'interval' => $plan['interval'] ?? 'none',
                'popular' => (bool) ($plan['popular'] ?? false),
                'subtotal_cents' => $price['subtotal_cents'],
                'tax_cents' => $price['tax_cents'],
                'amount_cents' => $price['amount_cents'],
                'currency' => $price['currency'],
                'entitlements' => $plan['entitlements'] ?? [],
            ];
        }

        return [
            'region' => $region,
            'currency' => $currency,
            'tax_rate' => (float) ($cfg['tax_rate'] ?? 0.0),
            'plans' => $plans,
        ];
    }

    /**
     * Create a PENDING Payment for a plan + region and hand it to the provider.
     *
     * @return array{payment: Payment, checkout: array{reference: string, checkout_url: string, amount_cents: int, currency: string, provider: string}}
     */
    public function startCheckout(User $user, string $planKey, ?string $region): array
    {
        $plan = config("sentrix.billing.plans.{$planKey}");
        if (! is_array($plan)) {
            throw ValidationException::withMessages(['plan_key' => ['Unknown plan.']]);
        }

        $resolvedRegion = $this->resolveRegion($region);
        $price = $this->priceFor($plan, $resolvedRegion);

        return $this->db->transaction(function () use ($user, $planKey, $resolvedRegion, $price): array {
            $payment = Payment::create([
                'user_id' => $user->getKey(),
                'reference' => 'pay_'.Str::lower(Str::random(20)),
                'plan_key' => $planKey,
                'amount_cents' => $price['amount_cents'],
                'currency' => $price['currency'],
                'status' => 'pending',
                'provider' => $this->payments->name(),
                'region' => $resolvedRegion,
                'metadata' => [
                    'subtotal_cents' => $price['subtotal_cents'],
                    'tax_cents' => $price['tax_cents'],
                ],
            ]);

            $checkout = $this->payments->createCheckout($payment);

            return ['payment' => $payment, 'checkout' => $checkout];
        });
    }
}
