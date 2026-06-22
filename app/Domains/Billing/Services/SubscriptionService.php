<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\PaymentProvider;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Subscription lifecycle + entitlements. Plan definitions are config-driven;
 * paid plan changes are charged via the PaymentProvider abstraction and recorded
 * as invoices. User-scoped (ADR-0001).
 */
final readonly class SubscriptionService
{
    public function __construct(
        private PaymentProvider $payments,
        private DatabaseManager $db,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function catalogue(): array
    {
        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('sentrix.billing.plans', []);

        return $plans;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function planConfig(string $key): ?array
    {
        return config("sentrix.billing.plans.{$key}");
    }

    public function current(User $user): Subscription
    {
        $subscription = Subscription::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['plan_key' => 'free', 'status' => 'active', 'auto_renew' => true],
        );

        // Reads/upserts respond 200, not 201, even on first-touch provisioning.
        $subscription->wasRecentlyCreated = false;

        return $subscription;
    }

    /**
     * @return list<string>
     */
    public function entitlementsFor(User $user): array
    {
        $plan = $this->planConfig($this->current($user)->plan_key) ?? [];

        /** @var list<string> $entitlements */
        $entitlements = $plan['entitlements'] ?? [];

        return $entitlements;
    }

    public function subscribe(User $user, string $planKey): Subscription
    {
        $plan = $this->planConfig($planKey);
        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => ['Unknown plan.']]);
        }

        $currency = (string) config('sentrix.billing.currency', 'USD');
        $amount = (int) ($plan['price_cents'] ?? 0);

        return $this->db->transaction(function () use ($user, $planKey, $plan, $amount, $currency): Subscription {
            $subscription = Subscription::query()->where('user_id', $user->getKey())->lockForUpdate()
                ->firstOrCreate(['user_id' => $user->getKey()], ['plan_key' => 'free', 'status' => 'active']);

            $paymentLabel = $subscription->payment_method_label;

            if ($amount > 0) {
                $reference = 'sub_'.Str::lower(Str::random(16));
                $result = $this->payments->charge($user, $amount, $currency, $reference);

                if (! $result->successful) {
                    throw ValidationException::withMessages(['payment' => [$result->failureReason ?? 'Payment failed.']]);
                }

                $paymentLabel = $result->paymentMethodLabel ?? $paymentLabel;

                Invoice::create([
                    'user_id' => $user->getKey(),
                    'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                    'plan_key' => $planKey,
                    'amount_cents' => $amount,
                    'currency' => $currency,
                    'status' => 'paid',
                    'issued_at' => now(),
                ]);
            }

            $subscription->forceFill([
                'plan_key' => $planKey,
                'status' => 'active',
                'auto_renew' => true,
                'payment_method_label' => $paymentLabel,
                'current_period_end' => match ((string) ($plan['interval'] ?? 'none')) {
                    'month' => now()->addMonth(),
                    'year' => now()->addYear(),
                    default => null,
                },
            ])->save();

            $subscription->refresh();
            $subscription->wasRecentlyCreated = false;

            return $subscription;
        });
    }

    /**
     * Activate/extend a user's subscription from a completed PSP Payment, and
     * record an invoice for the charge. Idempotent: a payment already marked
     * paid is a no-op (returns the current subscription) so confirming the same
     * reference twice via webhook + simulate never double-extends.
     *
     * The new period EXTENDS from the later of "now" and the current period end,
     * so a paid renewal stacks on remaining time instead of truncating it.
     */
    public function activateFromPayment(Payment $payment): Subscription
    {
        return $this->db->transaction(function () use ($payment): Subscription {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $subscription = Subscription::query()->where('user_id', $payment->user_id)->lockForUpdate()
                ->firstOrCreate(['user_id' => $payment->user_id], ['plan_key' => 'free', 'status' => 'active']);

            // Idempotency guard: already settled → activation already happened.
            if ($payment->status === 'paid') {
                $subscription->wasRecentlyCreated = false;

                return $subscription;
            }

            $plan = $this->planConfig($payment->plan_key) ?? [];

            $payment->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            Invoice::create([
                'user_id' => $payment->user_id,
                'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'plan_key' => $payment->plan_key,
                'amount_cents' => (int) $payment->amount_cents,
                'currency' => $payment->currency,
                'status' => 'paid',
                'issued_at' => now(),
            ]);

            $base = $subscription->current_period_end instanceof Carbon && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end->copy()
                : now();

            $subscription->forceFill([
                'plan_key' => $payment->plan_key,
                'status' => 'active',
                'auto_renew' => true,
                'current_period_end' => match ((string) ($plan['interval'] ?? 'none')) {
                    'month' => $base->addMonth(),
                    'year' => $base->addYear(),
                    default => null,
                },
            ])->save();

            $subscription->refresh();
            $subscription->wasRecentlyCreated = false;

            return $subscription;
        });
    }

    public function cancel(User $user): Subscription
    {
        $subscription = $this->current($user);
        // Keep benefits until the period ends; just stop renewal.
        $subscription->forceFill(['status' => 'cancelled', 'auto_renew' => false])->save();

        return $subscription->refresh();
    }

    public function setAutoRenew(User $user, bool $autoRenew): Subscription
    {
        $subscription = $this->current($user);
        $subscription->forceFill(['auto_renew' => $autoRenew])->save();

        return $subscription->refresh();
    }
}
