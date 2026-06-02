<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Http\Middleware;

use App\Domains\Organization\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Establishes the per-request organization "team" so all Spatie role and
 * permission checks resolve against the correct tenant.
 *
 * Resolution order:
 *   1. {organization} route parameter
 *   2. X-Organization request header
 *   3. the user's current_organization_id
 *
 * The resolved organization is bound into the container as the active scope and
 * stamped on the request for downstream controllers.
 */
final class SetOrganizationTeam
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $organizationId = $this->resolveOrganizationId($request, $user->current_organization_id);

        if ($organizationId === null) {
            return $next($request);
        }

        if (! $user->belongsToOrganization($organizationId)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not belong to this organization.');
        }

        // Scope every role/permission check for this request to the organization.
        $this->registrar->setPermissionsTeamId($organizationId);

        $organization = Organization::findOrFail($organizationId);
        app()->instance('organization.current', $organization);
        $request->attributes->set('organization', $organization);

        return $next($request);
    }

    private function resolveOrganizationId(Request $request, ?string $fallback): ?string
    {
        $routeParam = $request->route('organization');

        if ($routeParam instanceof Organization) {
            return $routeParam->getKey();
        }

        if (is_string($routeParam) && $routeParam !== '') {
            return $routeParam;
        }

        $header = $request->header('X-Organization');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return $fallback;
    }
}
