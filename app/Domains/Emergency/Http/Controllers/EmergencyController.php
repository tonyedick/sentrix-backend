<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Emergency\DTOs\TriggerEmergencyData;
use App\Domains\Emergency\Http\Requests\ResolveEmergencyRequest;
use App\Domains\Emergency\Http\Requests\TriggerEmergencyRequest;
use App\Domains\Emergency\Http\Resources\EmergencyResource;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Emergency\Services\EmergencyService;
use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\Models\Trip;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped emergency coordination. Operators (members who can
 * acknowledge) see every emergency; field users see only the ones they raised.
 */
final class EmergencyController extends Controller
{
    public function __construct(private readonly EmergencyService $emergencies) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::EmergenciesView->value),
            Response::HTTP_FORBIDDEN,
        );

        $user = $request->user();

        $emergencies = Emergency::query()
            ->where('organization_id', $organization->getKey())
            ->unless($this->isOperator($user), fn ($query) => $query->where('user_id', $user->getKey()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->string('severity')->value()))
            ->latest('triggered_at')
            ->paginate($this->perPage($request));

        return EmergencyResource::collection($emergencies);
    }

    public function store(TriggerEmergencyRequest $request, Organization $organization): JsonResponse
    {
        // A referenced trip must live in the same organization.
        if ($request->filled('trip_id')) {
            abort_unless(
                Trip::whereKey($request->string('trip_id')->value())
                    ->where('organization_id', $organization->getKey())
                    ->exists(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The referenced trip does not belong to this organization.',
            );
        }

        $emergency = $this->emergencies->trigger(
            $organization,
            TriggerEmergencyData::fromRequest($request),
            $request->user(),
        );

        return EmergencyResource::make($emergency)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Organization $organization, Emergency $emergency): EmergencyResource
    {
        $this->assertEmergencyInOrganization($organization, $emergency);

        abort_unless(
            $request->user()->can(DefaultPermission::EmergenciesView->value) && $this->canSee($request->user(), $emergency),
            Response::HTTP_FORBIDDEN,
        );

        return EmergencyResource::make($emergency);
    }

    public function acknowledge(Request $request, Organization $organization, Emergency $emergency): EmergencyResource
    {
        $this->assertEmergencyInOrganization($organization, $emergency);
        abort_unless($request->user()->can(DefaultPermission::EmergenciesAcknowledge->value), Response::HTTP_FORBIDDEN);

        return EmergencyResource::make($this->emergencies->acknowledge($emergency, $request->user()));
    }

    public function resolve(ResolveEmergencyRequest $request, Organization $organization, Emergency $emergency): EmergencyResource
    {
        $this->assertEmergencyInOrganization($organization, $emergency);

        return EmergencyResource::make(
            $this->emergencies->resolve($emergency, $request->user(), $request->input('resolution')),
        );
    }

    public function cancel(Request $request, Organization $organization, Emergency $emergency): EmergencyResource
    {
        $this->assertEmergencyInOrganization($organization, $emergency);

        // The person who raised it may cancel a false alarm; otherwise a
        // resolver-capable operator may stand it down.
        $user = $request->user();
        abort_unless(
            $emergency->user_id === $user->getKey() || $user->can(DefaultPermission::EmergenciesResolve->value),
            Response::HTTP_FORBIDDEN,
        );

        return EmergencyResource::make($this->emergencies->cancel($emergency, $user));
    }

    private function isOperator(User $user): bool
    {
        return $user->can(DefaultPermission::EmergenciesAcknowledge->value);
    }

    private function canSee(User $user, Emergency $emergency): bool
    {
        return $this->isOperator($user) || $emergency->user_id === $user->getKey();
    }

    private function assertEmergencyInOrganization(Organization $organization, Emergency $emergency): void
    {
        abort_if($emergency->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
