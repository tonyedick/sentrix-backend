<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\DTOs\StartTripData;
use App\Domains\Trip\DTOs\UpdateTripData;
use App\Domains\Trip\Http\Requests\StartTripRequest;
use App\Domains\Trip\Http\Requests\UpdateTripRequest;
use App\Domains\Trip\Http\Resources\TripResource;
use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Services\TripService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped trip management.
 *
 * Visibility model: "operators" (members who can view the member roster — i.e.
 * dispatchers and admins) see and manage every trip in the organization. Field
 * users see and manage only their own trips. The {organization} binding +
 * organization.team middleware guarantee tenant scoping.
 */
final class TripController extends Controller
{
    public function __construct(private readonly TripService $trips) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::TripsView->value),
            Response::HTTP_FORBIDDEN,
        );

        $user = $request->user();

        $trips = Trip::query()
            ->where('organization_id', $organization->getKey())
            ->unless($this->isOperator($user), fn ($query) => $query->where('user_id', $user->getKey()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate($this->perPage($request));

        return TripResource::collection($trips);
    }

    public function store(StartTripRequest $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        $targetUserId = $request->has('user_id') ? $request->string('user_id')->value() : $user->getKey();

        // Only operators may start a trip on behalf of another member.
        if ($targetUserId !== $user->getKey()) {
            abort_unless($this->isOperator($user), Response::HTTP_FORBIDDEN);
            abort_unless(
                User::whereKey($targetUserId)->first()?->belongsToOrganization($organization) === true,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The monitored user is not a member of this organization.',
            );
        }

        $trip = $this->trips->start(
            $organization,
            StartTripData::fromRequest($request, $targetUserId),
            $user,
        );

        return TripResource::make($trip)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Organization $organization, Trip $trip): TripResource
    {
        $this->assertTripInOrganization($organization, $trip);

        abort_unless(
            $request->user()->can(DefaultPermission::TripsView->value) && $this->canSee($request->user(), $trip),
            Response::HTTP_FORBIDDEN,
        );

        return TripResource::make($trip);
    }

    public function update(UpdateTripRequest $request, Organization $organization, Trip $trip): TripResource
    {
        $this->assertTripInOrganization($organization, $trip);
        $this->assertCanManage($request->user(), $trip);

        $trip = $this->trips->update($trip, UpdateTripData::fromRequest($request));

        return TripResource::make($trip);
    }

    public function complete(Request $request, Organization $organization, Trip $trip): TripResource
    {
        $this->assertTripInOrganization($organization, $trip);
        abort_unless($request->user()->can(DefaultPermission::TripsUpdate->value), Response::HTTP_FORBIDDEN);
        $this->assertCanManage($request->user(), $trip);

        return TripResource::make($this->trips->complete($trip, $request->user()));
    }

    public function cancel(Request $request, Organization $organization, Trip $trip): TripResource
    {
        $this->assertTripInOrganization($organization, $trip);
        abort_unless($request->user()->can(DefaultPermission::TripsCancel->value), Response::HTTP_FORBIDDEN);
        $this->assertCanManage($request->user(), $trip);

        return TripResource::make($this->trips->cancel($trip, $request->user()));
    }

    /**
     * Operators (roster-visible members: dispatchers/admins) manage all trips.
     */
    private function isOperator(User $user): bool
    {
        return $user->can(DefaultPermission::MembersView->value);
    }

    private function canSee(User $user, Trip $trip): bool
    {
        return $this->isOperator($user) || $trip->user_id === $user->getKey();
    }

    private function assertCanManage(User $user, Trip $trip): void
    {
        abort_unless($this->canSee($user, $trip), Response::HTTP_FORBIDDEN);
    }

    private function assertTripInOrganization(Organization $organization, Trip $trip): void
    {
        abort_if($trip->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
