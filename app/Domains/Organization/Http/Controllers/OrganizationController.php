<?php

declare(strict_types=1);

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Http\Requests\StoreOrganizationRequest;
use App\Domains\Organization\Http\Requests\UpdateOrganizationRequest;
use App\Domains\Organization\Http\Resources\OrganizationResource;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = $request->user()
            ->organizations()
            ->withCount('members')
            ->paginate($this->perPage($request));

        return OrganizationResource::collection($organizations);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->organizations->create(new CreateOrganizationData(
            name: $request->string('name')->value(),
            owner: $request->user(),
            slug: $request->has('slug') ? $request->string('slug')->value() : null,
        ));

        return OrganizationResource::make($organization)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Organization $organization): OrganizationResource
    {
        return OrganizationResource::make($organization->loadCount('members'));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        $organization = $this->organizations->update(
            $organization,
            $request->has('name') ? $request->string('name')->value() : null,
            $request->has('slug') ? $request->string('slug')->value() : null,
        );

        return OrganizationResource::make($organization);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        abort_unless(
            $request->user()->can(DefaultPermission::OrganizationDelete->value),
            Response::HTTP_FORBIDDEN,
        );

        $this->organizations->delete($organization);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Switch the authenticated user's active organization.
     */
    public function switch(Request $request, Organization $organization): OrganizationResource
    {
        $this->organizations->switchCurrent($request->user(), $organization);

        return OrganizationResource::make($organization);
    }
}
