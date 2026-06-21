<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Safe Rides — Marketplace & Send. User-scoped (ADR-0001): no organization, no
 * permission catalogue. Registered in the "Consumer modules" block of
 * bootstrap/providers.php (after Rides — it materialises a Rides Ride on accept).
 */
final class RidesMarketServiceProvider extends DomainServiceProvider
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
