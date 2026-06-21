<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\DTOs\IssuePassData;
use App\Domains\Access\Http\Requests\IssuePassRequest;
use App\Domains\Access\Http\Resources\PassResource;
use App\Domains\Access\Models\Pass;
use App\Domains\Access\Services\PassService;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped visitor passes. Hosts (residents/members) mint passes for
 * their own guests; managers (passes.manage) see and revoke any pass in the org.
 */
final class PassController extends Controller
{
    public function __construct(private readonly PassService $passes) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user->can(DefaultPermission::PassesView->value), Response::HTTP_FORBIDDEN);

        $passes = Pass::query()
            ->where('organization_id', $organization->getKey())
            // Without the org-wide "manage" ability, a host only sees their own passes.
            ->when(
                ! $user->can(DefaultPermission::PassesManage->value),
                fn ($query) => $query->where('host_id', $user->getKey()),
            )
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return PassResource::collection($passes);
    }

    public function store(IssuePassRequest $request, Organization $organization): JsonResponse
    {
        $pass = $this->passes->issue(
            $organization,
            $request->user(),
            IssuePassData::fromRequest($request),
        );

        return PassResource::make($pass)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function revoke(Request $request, Organization $organization, Pass $pass): PassResource
    {
        $this->assertPassInOrganization($organization, $pass);

        $user = $request->user();
        // The issuing host may revoke their own pass; otherwise org-wide manage is required.
        abort_unless(
            $pass->host_id === $user->getKey() || $user->can(DefaultPermission::PassesManage->value),
            Response::HTTP_FORBIDDEN,
        );

        return PassResource::make($this->passes->revoke($pass, $user));
    }

    private function assertPassInOrganization(Organization $organization, Pass $pass): void
    {
        abort_if($pass->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
