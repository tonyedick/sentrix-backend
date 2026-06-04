<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Http\Controllers;

use App\Domains\Authorization\Http\Requests\StoreRoleRequest;
use App\Domains\Authorization\Http\Requests\UpdateRoleRequest;
use App\Domains\Authorization\Http\Resources\RoleResource;
use App\Domains\Authorization\Models\Role;
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
 */
final class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        return RoleResource::collection(
            $this->roles->listForOrganization($organization, $this->perPage($request)),
        );
    }

    public function store(StoreRoleRequest $request, Organization $organization): JsonResponse
    {
        $role = $this->roles->create(
            $organization,
            $request->string('name')->value(),
            $request->array('permissions'),
        );

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Organization $organization, Role $role): RoleResource
    {
        return RoleResource::make($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Organization $organization, Role $role): RoleResource
    {
        $role = $this->roles->update(
            $role,
            $request->has('name') ? $request->string('name')->value() : null,
            $request->has('permissions') ? $request->array('permissions') : null,
        );

        return RoleResource::make($role);
    }

    public function destroy(Organization $organization, Role $role): JsonResponse
    {
        $this->roles->delete($role);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
