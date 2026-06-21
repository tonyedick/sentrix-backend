<?php

declare(strict_types=1);

namespace App\Domains\Trip\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Domains\Trip\Console\Commands\FlagOverdueTrips;
use App\Domains\Trip\Contracts\RouteProvider;
use App\Domains\Trip\Support\HaversineRouteProvider;

final class TripServiceProvider extends DomainServiceProvider
{
    public function register(): void
    {
        // Routing driver: built-in haversine by default; swap for a real engine
        // via config('sentrix.routing.driver').
        $this->app->bind(RouteProvider::class, static function (): RouteProvider {
            return match ((string) config('sentrix.routing.driver', 'haversine')) {
                default => new HaversineRouteProvider(),
            };
        });
    }

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
