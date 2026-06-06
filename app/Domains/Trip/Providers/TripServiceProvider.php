<?php

declare(strict_types=1);

namespace App\Domains\Trip\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Domains\Trip\Console\Commands\FlagOverdueTrips;

final class TripServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([FlagOverdueTrips::class]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
