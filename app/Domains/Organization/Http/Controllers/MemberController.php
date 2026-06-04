<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Controllers;

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
    public function __construct(private readonly MembershipService $memberships) {}

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
        $this->memberships->updateRole($organization, $user, $request->string('role')->value());

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
