<?php

declare(strict_types=1);

namespace App\Domains\Command\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Wires the Command (national responder) domain: its migrations and its
 * PLATFORM-scoped API routes.
 */
final class CommandServiceProvider extends DomainServiceProvider
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
