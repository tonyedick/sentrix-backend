<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Http\Controllers;

use App\Domains\DriverOnboarding\Models\Driver;
use App\Domains\Rides\Models\Ride;
use App\Domains\RidesOps\DTOs\ReassignData;
use App\Domains\RidesOps\DTOs\SurgeData;
use App\Domains\RidesOps\Http\Requests\ForceCancelRequest;
use App\Domains\RidesOps\Http\Requests\ReassignRequest;
use App\Domains\RidesOps\Http\Requests\SetSurgeRequest;
use App\Domains\RidesOps\Http\Requests\SuspendDriverRequest;
use App\Domains\RidesOps\Http\Resources\OpsDriverResource;
use App\Domains\RidesOps\Http\Resources\OpsRideResource;
use App\Domains\RidesOps\Services\RidesOpsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rides Ops — the platform/staff (SuperAdmin-gated) operations console.
 *
 * PLATFORM-scoped: routes are NOT organization-scoped. Every action is gated on
 * SuperAdmin (reads here in the controller, writes in their Form Requests).
 *
 * TODO: replace the SuperAdmin gate with a rides:ops / rides:dispatch
 * platform-staff role once those are modelled.
 */
final class RidesOpsController extends Controller
{
    public function __construct(private readonly RidesOpsService $ops) {}

    // ---- Reads -------------------------------------------------------------

    public function overview(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        // Cached briefly (Redis): several grouped aggregates over rides/drivers.
        $data = Cache::remember('rides-ops:overview', 30, fn () => $this->ops->overview());

        return response()->json(['data' => $data]);
    }

    public function rides(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $rides = Ride::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('requested_at')
            ->paginate($this->perPage($request));

        return OpsRideResource::collection($rides);
    }

    public function drivers(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $drivers = Driver::query()
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', $request->string('stage')->value()))
            ->when($request->filled('availability'), fn ($query) => $query->where('availability', $request->string('availability')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return OpsDriverResource::collection($drivers);
    }

    public function onboarding(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        return response()->json(['data' => $this->ops->onboardingFunnel()]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        $days = $request->filled('days') ? max(1, $request->integer('days')) : 1;

        return response()->json(['data' => $this->ops->analytics($days)]);
    }

    public function zones(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        return response()->json(['data' => $this->ops->zones()]);
    }

    public function live(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        return response()->json(['data' => $this->ops->live()]);
    }

    // ---- Writes ------------------------------------------------------------

    public function cancel(ForceCancelRequest $request, Ride $ride): OpsRideResource
    {
        $reason = $request->filled('reason')
            ? $request->string('reason')->trim()->value()
            : 'Cancelled by operations';

        return OpsRideResource::make($this->ops->forceCancel($ride, $request->user(), $reason));
    }

    public function reassign(ReassignRequest $request, Ride $ride): OpsRideResource
    {
        return OpsRideResource::make(
            $this->ops->reassign($ride, $request->user(), ReassignData::fromRequest($request)),
        );
    }

    public function suspend(SuspendDriverRequest $request, Driver $driver): OpsDriverResource
    {
        $reason = $request->filled('reason')
            ? $request->string('reason')->trim()->value()
            : 'Suspended by operations';

        return OpsDriverResource::make($this->ops->suspendDriver($driver, $request->user(), $reason));
    }

    public function reinstate(SuspendDriverRequest $request, Driver $driver): OpsDriverResource
    {
        return OpsDriverResource::make($this->ops->reinstateDriver($driver, $request->user()));
    }

    public function surge(SetSurgeRequest $request): JsonResponse
    {
        $result = $this->ops->setSurge(SurgeData::fromRequest($request), $request->user());

        return response()->json(['data' => $result]);
    }

    public function escalate(ForceCancelRequest $request, Ride $ride): JsonResponse
    {
        // ForceCancelRequest is reused purely for its SuperAdmin authorize() gate
        // (no body needed). Escalation is a SuperAdmin platform-staff action.
        $incident = $this->ops->escalate($ride, $request->user());

        abort_if($incident === null, Response::HTTP_CONFLICT, 'No HQ command structure available to receive the escalation.');

        return response()->json(['data' => [
            'ride_id' => $ride->getKey(),
            'command_incident_id' => $incident->getKey(),
            'agency_id' => $incident->agency_id,
            'command_id' => $incident->command_id,
            'category' => $incident->category->value,
            'severity' => $incident->severity->value,
            'status' => $incident->status->value,
        ]]);
    }

    private function assertSuperAdmin(Request $request): void
    {
        // TODO: replace with a rides:ops / rides:dispatch platform-staff role.
        abort_unless((bool) $request->user()?->isSuperAdmin(), Response::HTTP_FORBIDDEN);
    }
}
