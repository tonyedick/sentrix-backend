<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Safe Rides — Wallet & payments. User-scoped (ADR-0001): no organization, no
 * permission catalogue. Registered in the "Consumer modules" block of
 * bootstrap/providers.php.
 */
final class WalletServiceProvider extends DomainServiceProvider
{
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
