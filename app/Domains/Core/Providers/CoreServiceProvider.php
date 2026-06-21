<?php

declare(strict_types=1);

namespace App\Domains\Core\Providers;

use App\Domains\Core\Http\Middleware\AuthenticateCoreService;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Routing\Router;

/**
 * Wires the Core bridge: the service-token middleware alias and the domain's
 * routes. This domain owns no migrations (broadcast-only) — it is a thin proxy.
 */
final class CoreServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        // Service-token auth (X-Service-Token) for inbound /core/events — NOT
        // sanctum. Applied only to that route group.
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('core.service', AuthenticateCoreService::class);

        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
