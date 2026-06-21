<?php

declare(strict_types=1);

namespace App\Domains\Billing\Providers;

use App\Domains\Billing\Contracts\PaymentProvider;
use App\Domains\Billing\Support\LogPaymentProvider;
use App\Domains\Shared\Providers\DomainServiceProvider;

final class BillingServiceProvider extends DomainServiceProvider
{
    public function register(): void
    {
        // Payment driver: 'log' stub by default; swap for a real processor
        // (Paystack / Flutterwave / Stripe) via config('sentrix.billing.payment_driver').
        $this->app->bind(PaymentProvider::class, static function (): PaymentProvider {
            return match ((string) config('sentrix.billing.payment_driver', 'log')) {
                default => new LogPaymentProvider(),
            };
        });
    }

    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
