<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Domains\Tracking\Console\Commands\EnsureLocationPartitions;
use App\Domains\Tracking\Console\Commands\FlagStaleTrips;

final class TrackingServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([EnsureLocationPartitions::class, FlagStaleTrips::class]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
