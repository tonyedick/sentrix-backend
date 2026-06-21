<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Insurance\DTOs\CreatePolicyData;
use App\Domains\Insurance\Http\Requests\CreatePolicyRequest;
use App\Domains\Insurance\Http\Resources\PolicyResource;
use App\Domains\Insurance\Models\Policy;
use App\Domains\Insurance\Services\InsuranceService;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped insurance policies. Abilities are enforced per-action
 * (insurance.policies.view / insurance.policies.write).
 */
final class PolicyController extends Controller
{
    public function __construct(private readonly InsuranceService $insurance) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::InsurancePoliciesView->value),
            Response::HTTP_FORBIDDEN,
        );

        $policies = Policy::query()
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return PolicyResource::collection($policies);
    }

    public function store(CreatePolicyRequest $request, Organization $organization): JsonResponse
    {
        $policy = $this->insurance->createPolicy(
            $organization,
            $request->user(),
            CreatePolicyData::fromRequest($request),
        );

        return PolicyResource::make($policy)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
