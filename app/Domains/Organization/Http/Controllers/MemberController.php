<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\PermissionGuard;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Http\Requests\UpdateMemberRequest;
use App\Domains\Organization\Http\Resources\MemberResource;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class MemberController extends Controller
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly PermissionGuard $guard,
    ) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::MembersView->value),
            Response::HTTP_FORBIDDEN,
        );

        // Eager-load roles to avoid an N+1 when MemberResource resolves role
        // names. The team context is already set by the organization.team
        // middleware, so the roles relation resolves within this organization.
        $members = $organization->members()
            ->with('roles')
            ->paginate($this->perPage($request));

        return MemberResource::collection($members);
    }

    public function update(UpdateMemberRequest $request, Organization $organization, User $user): MemberResource
    {
        // The owner's role is fixed (they hold an implicit super-grant anyway);
        // it must not be altered by anyone, including other admins.
        abort_if(
            $organization->owner_id === $user->getKey(),
            Response::HTTP_FORBIDDEN,
            "The organization owner's role cannot be changed.",
        );

        $roleName = $request->string('role')->value();

        // The role is validated to exist in this organization; load it to check
        // the actor is not assigning privileges beyond their own (escalation).
        $role = Role::query()
            ->where('name', $roleName)
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        $this->guard->assertMayAssignRole($request->user(), $organization, $role);

        $this->memberships->updateRole($organization, $user, $roleName);

        return MemberResource::make($user->refresh());
    }

    public function destroy(Request $request, Organization $organization, User $user): JsonResponse
    {
        abort_unless(
            $request->user()->can(DefaultPermission::MembersRemove->value),
            Response::HTTP_FORBIDDEN,
        );

        $this->memberships->removeMember($organization, $user);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
