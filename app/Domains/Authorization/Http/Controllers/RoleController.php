<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Http\Controllers;

use App\Domains\Authorization\Http\Requests\StoreRoleRequest;
use App\Domains\Authorization\Http\Requests\UpdateRoleRequest;
use App\Domains\Authorization\Http\Resources\RoleResource;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\PermissionGuard;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped role management. The {organization} binding + the
 * organization.team middleware guarantee Spatie reads/writes the right tenant.
 *
 * Because {role} is bound by id globally, every single-role action first asserts
 * the role actually belongs to the active organization — otherwise a roles.manage
 * holder in one organization could read or mutate another tenant's roles (or the
 * NULL-team platform SuperAdmin role) by guessing an id.
 */
final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly PermissionGuard $guard,
    ) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        return RoleResource::collection(
            $this->roles->listForOrganization($organization, $this->perPage($request)),
        );
    }

    public function store(StoreRoleRequest $request, Organization $organization): JsonResponse
    {
        $permissions = $request->array('permissions');

        // No granting permissions you do not hold yourself.
        $this->guard->assertMayGrant($request->user(), $organization, $permissions);

        $role = $this->roles->create(
            $organization,
            $request->string('name')->value(),
            $permissions,
        );

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Organization $organization, Role $role): RoleResource
    {
        $this->assertRoleInOrganization($organization, $role);

        return RoleResource::make($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Organization $organization, Role $role): RoleResource
    {
        $this->assertRoleInOrganization($organization, $role);

        if ($request->has('permissions')) {
            $this->guard->assertMayGrant($request->user(), $organization, $request->array('permissions'));
        }

        $role = $this->roles->update(
            $role,
            $request->has('name') ? $request->string('name')->value() : null,
            $request->has('permissions') ? $request->array('permissions') : null,
        );

        return RoleResource::make($role);
    }

    public function destroy(Organization $organization, Role $role): JsonResponse
    {
        $this->assertRoleInOrganization($organization, $role);

        $this->roles->delete($role);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    private function assertRoleInOrganization(Organization $organization, Role $role): void
    {
        abort_if(
            $role->getAttribute('organization_id') !== $organization->getKey(),
            Response::HTTP_NOT_FOUND,
        );
    }
}
