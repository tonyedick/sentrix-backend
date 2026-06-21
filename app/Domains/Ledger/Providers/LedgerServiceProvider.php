<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Providers;

use App\Domains\Ledger\Console\Commands\FlagStaleSources;
use App\Domains\Ledger\Http\Middleware\AuthenticateLedgerSource;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Routing\Router;

final class LedgerServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        // Custom ingest auth (X-Ledger-Key) — applied only to the ingest route.
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ledger.key', AuthenticateLedgerSource::class);

        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlagStaleSources::class,
            ]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
