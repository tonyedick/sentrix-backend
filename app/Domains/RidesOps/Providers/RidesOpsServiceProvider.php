<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Rides Ops — the platform/staff Safe Rides operations console. Reads the
 * Rides, DriverOnboarding and RidesMarket domains and owns one table
 * (surge_overrides). The base provider auto-loads the domain migrations + the
 * api routes file.
 */
final class RidesOpsServiceProvider extends DomainServiceProvider
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
