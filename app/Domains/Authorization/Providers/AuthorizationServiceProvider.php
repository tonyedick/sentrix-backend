<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Providers;

use App\Domains\Authorization\Http\Middleware\SetOrganizationTeam;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

final class AuthorizationServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        Route::aliasMiddleware('organization.team', SetOrganizationTeam::class);

        Gate::before(static function (User $user, string $ability): ?bool {
            // 1. Platform-global SuperAdmin holds every ability everywhere,
            //    regardless of the active organization (team) context.
            if ($user->isSuperAdmin()) {
                return true;
            }

            // 2. An organization owner implicitly holds every ability within
            //    their own organization. The active organization is resolved by
            //    the SetOrganizationTeam middleware.
            if (app()->bound('organization.current')) {
                $organization = app('organization.current');

                if ($organization instanceof Organization
                    && $organization->owner_id === $user->getKey()) {
                    return true;
                }
            }

            return null;
        });

        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
