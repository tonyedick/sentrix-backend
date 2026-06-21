<?php

declare(strict_types=1);

namespace App\Domains\Intel\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

/**
 * Intel is a read-only reporting/analytics slice. It owns no primary entity and
 * therefore no migrations — loadDomainMigrations() is a no-op when the
 * Database/Migrations directory is absent (see DomainServiceProvider), and is
 * omitted here, mirroring the migration-less Core domain.
 */
final class IntelServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
