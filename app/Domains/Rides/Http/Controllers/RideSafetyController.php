<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Controllers;

use App\Domains\Rides\Http\Requests\BankEvidenceRequest;
use App\Domains\Rides\Http\Requests\CheckInRequest;
use App\Domains\Rides\Http\Resources\RideEvidenceResource;
use App\Domains\Rides\Http\Resources\RideSafetyResource;
use App\Domains\Rides\Models\Ride;
use App\Domains\Rides\Services\RideService;
use App\Domains\Rides\Support\Enums\EvidenceKind;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In-ride safety (arm / state / SOS / evidence / check-in). User-scoped; each
 * action asserts the ride belongs to the authenticated rider.
 */
final class RideSafetyController extends Controller
{
    public function __construct(private readonly RideService $rides) {}

    public function arm(Request $request, Ride $ride): RideSafetyResource
    {
        $this->assertOwned($request, $ride);

        return RideSafetyResource::make($this->rides->arm($ride));
    }

    public function show(Request $request, Ride $ride): RideSafetyResource
    {
        $this->assertOwned($request, $ride);

        return RideSafetyResource::make($this->rides->safetyFor($ride));
    }

    public function sos(Request $request, Ride $ride): RideSafetyResource
    {
        $this->assertOwned($request, $ride);

        return RideSafetyResource::make($this->rides->sos($ride));
    }

    public function evidence(BankEvidenceRequest $request, Ride $ride): JsonResponse
    {
        $this->assertOwned($request, $ride);

        $evidence = $this->rides->bankEvidence(
            $ride,
            EvidenceKind::from($request->string('kind')->value()),
            $request->string('url')->value(),
        );

        return RideEvidenceResource::make($evidence)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function checkIn(CheckInRequest $request, Ride $ride): RideSafetyResource
    {
        $this->assertOwned($request, $ride);

        return RideSafetyResource::make($this->rides->checkIn($ride, $request->boolean('ok')));
    }

    private function assertOwned(Request $request, Ride $ride): void
    {
        abort_if($ride->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
