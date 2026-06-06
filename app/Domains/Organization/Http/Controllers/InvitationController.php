<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\PermissionGuard;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\DTOs\InviteMemberData;
use App\Domains\Organization\Http\Requests\InviteMemberRequest;
use App\Domains\Organization\Http\Resources\InvitationResource;
use App\Domains\Organization\Http\Resources\OrganizationResource;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\OrganizationInvitation;
use App\Domains\Organization\Services\InvitationService;
use App\Domains\Organization\Services\MembershipService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly PermissionGuard $guard,
    ) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::MembersView->value),
            Response::HTTP_FORBIDDEN,
        );

        return InvitationResource::collection(
            $organization->invitations()->latest()->paginate($this->perPage($request)),
        );
    }

    public function store(InviteMemberRequest $request, Organization $organization): JsonResponse
    {
        // The role is validated to exist in this organization; block inviting at
        // a privilege level the inviter does not themselves hold (escalation).
        $role = Role::query()
            ->where('name', $request->string('role')->value())
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        $this->guard->assertMayAssignRole($request->user(), $organization, $role);

        $invitation = $this->invitations->invite(
            $organization,
            InviteMemberData::fromRequest($request),
            $request->user(),
        );

        return InvitationResource::make($invitation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Organization $organization, OrganizationInvitation $invitation): JsonResponse
    {
        abort_unless(
            $request->user()->can(DefaultPermission::MembersInvite->value),
            Response::HTTP_FORBIDDEN,
        );

        $this->invitations->revoke($invitation);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Accept an invitation as the authenticated user (token-bound, not
     * organization-scoped — the user is not yet a member).
     */
    public function accept(Request $request, OrganizationInvitation $invitation, MembershipService $memberships): OrganizationResource
    {
        $organization = $this->invitations->accept($invitation, $request->user(), $memberships);

        return OrganizationResource::make($organization);
    }
}
