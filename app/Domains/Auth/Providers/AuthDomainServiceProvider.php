<?php

declare(strict_types=1);

namespace App\Domains\Auth\Providers;

use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class AuthDomainServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) ($request->input('email') ?: $request->ip())));
    }
}
