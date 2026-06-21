<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Organization\Models\Organization;
use App\Domains\Tracking\DTOs\LocationFix;
use App\Domains\Tracking\Http\Requests\IngestLocationsRequest;
use App\Domains\Tracking\Http\Resources\ProximityTripResource;
use App\Domains\Tracking\Http\Resources\TripLocationResource;
use App\Domains\Tracking\Http\Resources\TripPositionResource;
use App\Domains\Tracking\Models\TripLocation;
use App\Domains\Tracking\Services\LocationIngestService;
use App\Domains\Tracking\Services\ProximityService;
use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Support\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trip location tracking. Ingest is the device (the trip's monitored user)
 * reporting its own positions; reads are the historical track, the live org-wide
 * position snapshot, and PostGIS proximity queries.
 */
final class LocationController extends Controller
{
    public function __construct(
        private readonly LocationIngestService $ingest,
        private readonly ProximityService $proximity,
    ) {}

    /** Default proximity radius in metres when none is supplied. */
    private const DEFAULT_RADIUS_METERS = 5000;

    /**
     * Batch-ingest fixes for a trip. Only the trip's own user (the reporting
     * device) may post, and only while the trip is live.
     */
    public function store(IngestLocationsRequest $request, Organization $organization, Trip $trip): JsonResponse
    {
        $this->assertTripInOrganization($organization, $trip);

        abort_unless($trip->user_id === $request->user()->getKey(), Response::HTTP_FORBIDDEN);
        abort_if($trip->status->isTerminal(), Response::HTTP_UNPROCESSABLE_ENTITY, 'This trip is no longer accepting locations.');

        $fixes = array_map(
            static fn (array $fix): LocationFix => LocationFix::fromArray($fix),
            $request->array('fixes'),
        );

        $stored = $this->ingest->ingest($trip, $fixes);

        return response()->json([
            'message' => 'Locations recorded.',
            'data' => ['received' => count($fixes), 'stored' => $stored],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * The trip's historical track, newest first.
     */
    public function index(Request $request, Organization $organization, Trip $trip): AnonymousResourceCollection
    {
        $this->assertTripInOrganization($organization, $trip);

        abort_unless(
            $request->user()->can(DefaultPermission::TrackingView->value) && $this->canSee($request->user(), $trip),
            Response::HTTP_FORBIDDEN,
        );

        $locations = TripLocation::query()
            ->where('trip_id', $trip->getKey())
            ->orderByDesc('recorded_at')
            ->paginate($this->perPage($request));

        return TripLocationResource::collection($locations);
    }

    /**
     * Live snapshot: the current position of every active/overdue trip in the
     * organization that has reported one. Operator-facing (the dispatcher map).
     */
    public function latest(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::TrackingView->value) && $this->isOperator($request->user()),
            Response::HTTP_FORBIDDEN,
        );

        $positions = Trip::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('status', [TripStatus::Active->value, TripStatus::Overdue->value])
            ->whereNotNull('last_location_at')
            ->paginate($this->perPage($request));

        return TripPositionResource::collection($positions);
    }

    /**
     * Active trips within a radius of a point, nearest first. Operator-facing.
     */
    public function nearby(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->assertCanQueryProximity($request->user());

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);

        $trips = $this->proximity->nearbyActiveTrips(
            $organization,
            (float) $validated['lat'],
            (float) $validated['lng'],
            (int) ($validated['radius'] ?? self::DEFAULT_RADIUS_METERS),
            $this->perPage($request),
        );

        return ProximityTripResource::collection($trips);
    }

    /**
     * Active trips near an emergency's location — "who is close to this incident".
     * Excludes the emergency's own trip.
     */
    public function nearbyToEmergency(Request $request, Organization $organization, Emergency $emergency): AnonymousResourceCollection
    {
        abort_if($emergency->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
        $this->assertCanQueryProximity($request->user());

        abort_if(
            $emergency->lat === null || $emergency->lng === null,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'This emergency has no location to search around.',
        );

        $validated = $request->validate([
            'radius' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);

        $trips = $this->proximity->nearbyActiveTrips(
            $organization,
            (float) $emergency->lat,
            (float) $emergency->lng,
            (int) ($validated['radius'] ?? self::DEFAULT_RADIUS_METERS),
            $this->perPage($request),
            excludeTripId: $emergency->trip_id,
        );

        return ProximityTripResource::collection($trips);
    }

    private function assertCanQueryProximity(User $user): void
    {
        abort_unless(
            $user->can(DefaultPermission::TrackingView->value) && $this->isOperator($user),
            Response::HTTP_FORBIDDEN,
        );
    }

    private function isOperator(User $user): bool
    {
        return $user->can(DefaultPermission::MembersView->value);
    }

    private function canSee(User $user, Trip $trip): bool
    {
        return $this->isOperator($user) || $trip->user_id === $user->getKey();
    }

    private function assertTripInOrganization(Organization $organization, Trip $trip): void
    {
        abort_if($trip->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
