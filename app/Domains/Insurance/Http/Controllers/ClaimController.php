<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Insurance\DTOs\FileClaimData;
use App\Domains\Insurance\Http\Requests\DecideClaimRequest;
use App\Domains\Insurance\Http\Requests\FileClaimRequest;
use App\Domains\Insurance\Http\Resources\ClaimResource;
use App\Domains\Insurance\Models\Claim;
use App\Domains\Insurance\Models\Policy;
use App\Domains\Insurance\Services\InsuranceService;
use App\Domains\Insurance\Support\Enums\ClaimStatus;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped insurance claims with an approve/reject decision step.
 * Abilities are enforced per-action (insurance.claims.view / insurance.claims.file
 * / insurance.claims.adjust).
 */
final class ClaimController extends Controller
{
    public function __construct(private readonly InsuranceService $insurance) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::InsuranceClaimsView->value),
            Response::HTTP_FORBIDDEN,
        );

        $claims = Claim::query()
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('policy_id'), fn ($query) => $query->where('policy_id', $request->string('policy_id')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return ClaimResource::collection($claims);
    }

    public function store(FileClaimRequest $request, Organization $organization): JsonResponse
    {
        $policy = Policy::query()
            ->whereKey($request->string('policy_id')->value())
            ->where('organization_id', $organization->getKey())
            ->first();

        // The referenced policy must belong to this organization.
        abort_if($policy === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'The referenced policy does not belong to this organization.');

        $claim = $this->insurance->fileClaim(
            $organization,
            $request->user(),
            $policy,
            FileClaimData::fromRequest($request),
        );

        return ClaimResource::make($claim)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function decide(DecideClaimRequest $request, Organization $organization, Claim $claim): ClaimResource
    {
        $this->assertClaimInOrganization($organization, $claim);

        $outcome = ClaimStatus::from($request->string('decision')->value());

        return ClaimResource::make($this->insurance->decideClaim($claim, $request->user(), $outcome));
    }

    private function assertClaimInOrganization(Organization $organization, Claim $claim): void
    {
        abort_if($claim->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
