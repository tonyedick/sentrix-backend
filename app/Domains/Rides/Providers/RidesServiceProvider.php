<?php

declare(strict_types=1);

namespace App\Domains\Rides\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

final class RidesServiceProvider extends DomainServiceProvider
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
