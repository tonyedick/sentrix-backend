<?php

declare(strict_types=1);

namespace App\Domains\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider every domain extends.
 *
 * Keeps route + migration registration uniform across modules so a new
 * domain only declares its path and gets consistent wiring for free.
 */
abstract class DomainServiceProvider extends ServiceProvider
{
    /**
     * Absolute path to the domain root (the directory containing this domain's
     * Routes/, Database/, etc.). Implemented by each concrete provider.
     */
    abstract protected function domainPath(): string;

    protected function loadDomainApiRoutes(): void
    {
        $routes = $this->domainPath().'/Routes/api.php';

        if (is_file($routes)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routes);
        }
    }

    protected function loadDomainMigrations(): void
    {
        $migrations = $this->domainPath().'/Database/Migrations';

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }
}
