<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;

final class InsuranceServiceProvider extends DomainServiceProvider
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
