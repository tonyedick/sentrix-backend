<?php

declare(strict_types=1);

namespace App\Domains\Retention\Providers;

use App\Domains\Retention\Console\Commands\SweepRetention;
use App\Domains\Shared\Providers\DomainServiceProvider;

final class RetentionServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([SweepRetention::class]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
