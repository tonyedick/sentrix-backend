<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Safe Rides — Driver onboarding. Vetting centers are seeded directly in the
 * domain migration, so no DatabaseSeeder is wired here.
 */
final class DriverOnboardingServiceProvider extends DomainServiceProvider
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
