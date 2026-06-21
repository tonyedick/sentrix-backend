<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Controllers;

use App\Domains\Rides\DTOs\QuoteData;
use App\Domains\Rides\DTOs\RequestRideData;
use App\Domains\Rides\Http\Requests\CancelRideRequest;
use App\Domains\Rides\Http\Requests\CompleteRideRequest;
use App\Domains\Rides\Http\Requests\QuoteRideRequest;
use App\Domains\Rides\Http\Requests\RequestRideRequest;
use App\Domains\Rides\Http\Resources\RideResource;
use App\Domains\Rides\Models\Ride;
use App\Domains\Rides\Services\RideService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ride booking + lifecycle. User-scoped (ADR-0001): every ride belongs to the
 * authenticated rider; record-level ownership is asserted on each {ride} action.
 */
final class RideController extends Controller
{
    public function __construct(private readonly RideService $rides) {}

    public function quote(QuoteRideRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->rides->quote(QuoteData::fromRequest($request))]);
    }

    public function request(RequestRideRequest $request): JsonResponse
    {
        $ride = $this->rides->request($request->user(), RequestRideData::fromRequest($request));

        return RideResource::make($ride)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $rides = Ride::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('requested_at')
            ->paginate($this->perPage($request));

        return RideResource::collection($rides);
    }

    public function cancelReasons(): JsonResponse
    {
        return response()->json(['data' => $this->rides->cancelReasons()]);
    }

    public function show(Request $request, Ride $ride): RideResource
    {
        $this->assertOwned($request, $ride);

        return RideResource::make($ride);
    }

    public function track(Request $request, Ride $ride): JsonResponse
    {
        $this->assertOwned($request, $ride);

        return response()->json(['data' => $this->rides->track($ride)]);
    }

    public function cancel(CancelRideRequest $request, Ride $ride): RideResource
    {
        $this->assertOwned($request, $ride);

        return RideResource::make($this->rides->cancel($ride, $request->string('reason')->value()));
    }

    public function complete(CompleteRideRequest $request, Ride $ride): RideResource
    {
        $this->assertOwned($request, $ride);

        return RideResource::make($this->rides->complete(
            $ride,
            $request->filled('rating') ? $request->integer('rating') : null,
            $request->integer('tip_cents', 0),
        ));
    }

    public function receipt(Request $request, Ride $ride): JsonResponse
    {
        $this->assertOwned($request, $ride);

        return response()->json(['data' => $this->rides->receipt($ride)]);
    }

    private function assertOwned(Request $request, Ride $ride): void
    {
        abort_if($ride->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
