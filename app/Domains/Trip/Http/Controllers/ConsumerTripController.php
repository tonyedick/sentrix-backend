<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Controllers;

use App\Domains\Organization\Services\ServingOrganizationResolver;
use App\Domains\Trip\DTOs\StartTripData;
use App\Domains\Trip\Http\Requests\IngestTripLocationsRequest;
use App\Domains\Trip\Http\Requests\StartConsumerTripRequest;
use App\Domains\Trip\Http\Resources\TripResource;
use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Services\TripService;
use App\Domains\Tracking\DTOs\LocationFix;
use App\Domains\Tracking\Services\LocationIngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consumer trips (user-scoped, ADR-0001). A monitored journey owned by the
 * authenticated user and watched by the resolved serving org, reusing the
 * existing TripService + tracking ingest. The consumer never sees an org id.
 */
final class ConsumerTripController extends Controller
{
    public function __construct(
        private readonly TripService $trips,
        private readonly LocationIngestService $locations,
        private readonly ServingOrganizationResolver $servingOrg,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $trips = Trip::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('started_at')
            ->paginate($this->perPage($request));

        return TripResource::collection($trips);
    }

    public function store(StartConsumerTripRequest $request): JsonResponse
    {
        $user = $request->user();
        $originLat = $request->has('origin_lat') ? (float) $request->input('origin_lat') : null;
        $originLng = $request->has('origin_lng') ? (float) $request->input('origin_lng') : null;

        $organization = $this->servingOrg->resolve($originLat, $originLng);

        $data = new StartTripData(
            userId: $user->getKey(),
            originLabel: $request->input('origin_label'),
            originLat: $originLat,
            originLng: $originLng,
            destinationLabel: $request->input('destination_label'),
            destinationLat: $request->has('destination_lat') ? (float) $request->input('destination_lat') : null,
            destinationLng: $request->has('destination_lng') ? (float) $request->input('destination_lng') : null,
            expectedArrivalAt: $request->filled('expected_arrival_at')
                ? Carbon::parse((string) $request->input('expected_arrival_at'))
                : null,
            notes: $request->input('notes'),
        );

        $trip = $this->trips->start($organization, $data, $user);

        return TripResource::make($trip)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Trip $trip): TripResource
    {
        $this->assertOwned($request, $trip);

        return TripResource::make($trip);
    }

    public function complete(Request $request, Trip $trip): TripResource
    {
        $this->assertOwned($request, $trip);

        return TripResource::make($this->trips->complete($trip, $request->user()));
    }

    public function cancel(Request $request, Trip $trip): TripResource
    {
        $this->assertOwned($request, $trip);

        return TripResource::make($this->trips->cancel($trip, $request->user()));
    }

    public function storeLocations(IngestTripLocationsRequest $request, Trip $trip): JsonResponse
    {
        $this->assertOwned($request, $trip);

        $fixes = array_map(
            static fn (array $fix): LocationFix => LocationFix::fromArray($fix),
            $request->validated('fixes'),
        );
        $stored = $this->locations->ingest($trip, $fixes);

        return response()->json([
            'stored' => $stored,
            'received' => count($fixes),
        ]);
    }

    private function assertOwned(Request $request, Trip $trip): void
    {
        abort_if($trip->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
