<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Requests\LogGateRequest;
use App\Domains\Access\Http\Requests\ScanGateRequest;
use App\Domains\Access\Http\Resources\GateEventResource;
use App\Domains\Access\Http\Resources\PassResource;
use App\Domains\Access\Models\GateEvent;
use App\Domains\Access\Services\GateService;
use App\Domains\Access\Support\Enums\GateDirection;
use App\Domains\Access\Support\Enums\GateResult;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate surface: verify a visitor code (scan), read the gate log, and append
 * a manual entry. Every scan appends an immutable gate event.
 */
final class GateController extends Controller
{
    public function __construct(private readonly GateService $gate) {}

    public function scan(ScanGateRequest $request, Organization $organization): JsonResponse
    {
        $result = $this->gate->scan(
            $organization,
            $request->string('code')->value(),
            $request->string('gate', 'Main Gate')->value(),
            GateDirection::from($request->string('direction', GateDirection::In->value)->value()),
            $request->user(),
        );

        /** @var GateEvent $event */
        $event = $result['event'];

        return response()->json([
            'message' => $event->result === GateResult::Granted ? 'Entry granted.' : 'Entry denied.',
            'data' => [
                'result' => $event->result->value,
                'reason' => $event->reason,
                'pass' => $result['pass'] !== null ? PassResource::make($result['pass'])->resolve($request) : null,
                'event' => GateEventResource::make($event)->resolve($request),
            ],
        ]);
    }

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::GateView->value), Response::HTTP_FORBIDDEN);

        $events = GateEvent::query()
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('result'), fn ($query) => $query->where('result', $request->string('result')->value()))
            ->latest('recorded_at')
            ->paginate($this->perPage($request));

        return GateEventResource::collection($events);
    }

    public function store(LogGateRequest $request, Organization $organization): JsonResponse
    {
        $event = $this->gate->log(
            $organization,
            $request->user(),
            $request->string('gate', 'Main Gate')->value(),
            GateDirection::from($request->string('direction', GateDirection::In->value)->value()),
            $request->input('visitor_name'),
            GateResult::from($request->string('result', GateResult::Granted->value)->value()),
        );

        return GateEventResource::make($event)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
