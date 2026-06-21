<?php

declare(strict_types=1);

namespace App\Domains\Crm\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Wires the CRM domain (the platform-scoped sales -> onboarding -> tenant
 * pipeline) into the application: its migrations and its API routes.
 */
final class CrmServiceProvider extends DomainServiceProvider
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
