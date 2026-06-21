<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Http\Controllers;

use App\Domains\Emergency\DTOs\TriggerEmergencyData;
use App\Domains\Emergency\Http\Requests\TriggerSosRequest;
use App\Domains\Emergency\Http\Resources\EmergencyResource;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Emergency\Services\EmergencyService;
use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use App\Domains\Organization\Services\ServingOrganizationResolver;
use App\Domains\Trip\Models\Trip;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consumer SOS (user-scoped, ADR-0001). A trigger resolves the serving
 * organization server-side and hands off to the existing EmergencyService, so
 * the emergency flows into the operational escalation/dispatch pipeline and the
 * dispatcher dashboard. The consumer never sees an organization id.
 */
final class ConsumerEmergencyController extends Controller
{
    public function __construct(
        private readonly EmergencyService $emergencies,
        private readonly ServingOrganizationResolver $servingOrg,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $emergencies = Emergency::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('triggered_at')
            ->paginate($this->perPage($request));

        return EmergencyResource::collection($emergencies);
    }

    public function store(TriggerSosRequest $request): JsonResponse
    {
        $user = $request->user();
        $lat = $request->has('lat') ? (float) $request->input('lat') : null;
        $lng = $request->has('lng') ? (float) $request->input('lng') : null;

        // Idempotency: panic taps double-fire. A repeat with the same key within
        // the window returns the original emergency instead of raising another.
        $idempotencyKey = $request->header('Idempotency-Key');
        $cacheKey = $idempotencyKey !== null ? "sos:{$user->getKey()}:{$idempotencyKey}" : null;
        if ($cacheKey !== null && ($existingId = Cache::get($cacheKey)) !== null) {
            return EmergencyResource::make(Emergency::findOrFail($existingId))->response();
        }

        // A referenced trip must belong to this user.
        if ($request->filled('trip_id')) {
            abort_unless(
                Trip::query()->whereKey($request->string('trip_id')->value())->where('user_id', $user->getKey())->exists(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The referenced trip does not belong to you.',
            );
        }

        $organization = $this->servingOrg->resolve($lat, $lng);

        $data = new TriggerEmergencyData(
            severity: EmergencySeverity::from($request->string('severity', EmergencySeverity::High->value)->value()),
            message: $request->input('message'),
            lat: $lat,
            lng: $lng,
            tripId: $request->input('trip_id'),
        );

        $emergency = $this->emergencies->trigger($organization, $data, $user);

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $emergency->getKey(), now()->addMinutes(10));
        }

        return EmergencyResource::make($emergency)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Emergency $emergency): EmergencyResource
    {
        $this->assertOwned($request, $emergency);

        return EmergencyResource::make($emergency);
    }

    public function cancel(Request $request, Emergency $emergency): EmergencyResource
    {
        $this->assertOwned($request, $emergency);

        return EmergencyResource::make($this->emergencies->cancel($emergency, $request->user()));
    }

    private function assertOwned(Request $request, Emergency $emergency): void
    {
        abort_if($emergency->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
