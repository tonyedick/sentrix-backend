<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Providers;

use App\Domains\Authorization\Http\Middleware\SetOrganizationTeam;
use App\Domains\Authorization\Support\Enums\DefaultRole;
use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

final class AuthorizationServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        Route::aliasMiddleware('organization.team', SetOrganizationTeam::class);

        // Organization owners implicitly hold every permission within their own
        // organization (the team context is already set by middleware).
        Gate::before(static function (User $user, string $ability): ?bool {
            return $user->hasRole(DefaultRole::Owner->value) ? true : null;
        });

        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
