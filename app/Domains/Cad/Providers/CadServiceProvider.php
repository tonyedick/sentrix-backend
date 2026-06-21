<?php

declare(strict_types=1);

namespace App\Domains\Cad\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Wires the CAD (Computer-Aided Dispatch) domain: its migrations and its
 * PLATFORM-scoped API routes (units / AVL / dispatch / BOLOs), which sit under
 * the shared `v1/command` prefix alongside the Command domain.
 */
final class CadServiceProvider extends DomainServiceProvider
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
