<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Http\Controllers;

use App\Domains\Cad\Models\Unit;
use App\Domains\Cad\Models\UnitDispatch;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Coordination\DTOs\RecordDutyData;
use App\Domains\Coordination\DTOs\RequestMutualAidData;
use App\Domains\Coordination\DTOs\RouteTaskingData;
use App\Domains\Coordination\DTOs\SendUnitMessageData;
use App\Domains\Coordination\Http\Requests\RecordDutyRequest;
use App\Domains\Coordination\Http\Requests\RequestMutualAidRequest;
use App\Domains\Coordination\Http\Requests\RouteTaskingRequest;
use App\Domains\Coordination\Http\Requests\SendUnitMessageRequest;
use App\Domains\Coordination\Http\Resources\DutyEntryResource;
use App\Domains\Coordination\Http\Resources\MutualAidRequestResource;
use App\Domains\Coordination\Http\Resources\TaskingResource;
use App\Domains\Coordination\Http\Resources\UnitMessageResource;
use App\Domains\Coordination\Models\DutyEntry;
use App\Domains\Coordination\Models\MutualAidRequest;
use App\Domains\Coordination\Models\Tasking;
use App\Domains\Coordination\Models\UnitMessage;
use App\Domains\Coordination\Services\CoordinationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coordination across the Security Command area: mutual aid, unit comms, command
 * analytics, and the duty/taskings staffing layer. Platform-scoped (SuperAdmin).
 *
 * TODO: accept command roles (dispatch_coordinator/monitor/auditor) once a
 * platform-staff RBAC layer exists.
 */
final class CoordinationController extends Controller
{
    public function __construct(private readonly CoordinationService $coordination) {}

    private function assertSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), Response::HTTP_FORBIDDEN);
    }

    // ---- Mutual aid ----------------------------------------------------------

    public function requestAid(RequestMutualAidRequest $request): JsonResponse
    {
        $aid = $this->coordination->requestAid(
            RequestMutualAidData::fromRequest($request),
            $request->user()->getKey(),
        );

        return MutualAidRequestResource::make($aid)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function listAid(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $aid = MutualAidRequest::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when(
                $request->filled('command_incident_id') && Str::isUuid((string) $request->input('command_incident_id')),
                fn ($q) => $q->where('command_incident_id', $request->string('command_incident_id')->value()),
            )
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return MutualAidRequestResource::collection($aid);
    }

    public function acceptAid(Request $request, MutualAidRequest $mutualAidRequest): MutualAidRequestResource
    {
        $this->assertSuperAdmin($request);

        return MutualAidRequestResource::make($this->coordination->acceptAid($mutualAidRequest));
    }

    public function declineAid(Request $request, MutualAidRequest $mutualAidRequest): MutualAidRequestResource
    {
        $this->assertSuperAdmin($request);

        return MutualAidRequestResource::make($this->coordination->declineAid($mutualAidRequest));
    }

    // ---- Unit comms ----------------------------------------------------------

    public function sendMessage(SendUnitMessageRequest $request, Unit $unit): JsonResponse
    {
        $message = $this->coordination->sendUnitMessage(
            $unit,
            SendUnitMessageData::fromRequest($request),
            $request->user()->getKey(),
        );

        return UnitMessageResource::make($message)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function thread(Request $request, Unit $unit): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        // Reading the thread marks inbound (field → desk) unread messages as read.
        $this->coordination->markThreadRead($unit);

        $messages = UnitMessage::query()
            ->where('unit_id', $unit->getKey())
            ->orderBy('created_at')
            ->paginate($this->perPage($request));

        return UnitMessageResource::collection($messages);
    }

    // ---- Command analytics (read-only computed) ------------------------------

    public function analytics(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        $days = max(1, min($request->integer('days', 30), 365));
        $since = now()->subDays($days);

        $incidents = CommandIncident::query()
            ->where('opened_at', '>=', $since)
            ->when(
                $request->filled('agency_id') && Str::isUuid((string) $request->input('agency_id')),
                fn ($q) => $q->where('agency_id', $request->string('agency_id')->value()),
            )
            ->get(['id', 'status', 'category', 'severity', 'opened_at', 'sla_dispatch_due_at']);

        // Earliest dispatch per incident, for response-time + SLA compliance.
        $earliestDispatch = UnitDispatch::query()
            ->whereIn('command_incident_id', $incidents->pluck('id'))
            ->get(['command_incident_id', 'dispatched_at'])
            ->groupBy('command_incident_id')
            ->map(fn ($rows) => $rows->pluck('dispatched_at')->filter()->min());

        $responseSeconds = [];
        $slaConsidered = 0;
        $slaMet = 0;

        foreach ($incidents as $incident) {
            $dispatchedAt = $earliestDispatch->get($incident->id);
            if ($dispatchedAt === null || $incident->opened_at === null) {
                continue;
            }
            // Carbon 3's diffInSeconds() returns a float; normalise to whole seconds.
            $responseSeconds[] = (int) abs($incident->opened_at->diffInSeconds($dispatchedAt));

            if ($incident->sla_dispatch_due_at !== null) {
                $slaConsidered++;
                if ($dispatchedAt->lessThanOrEqualTo($incident->sla_dispatch_due_at)) {
                    $slaMet++;
                }
            }
        }

        $totalUnits = Unit::query()->count();
        $assignedUnits = Unit::query()->where('status', 'assigned')->count();

        return response()->json(['data' => [
            'window_days' => $days,
            'incidents' => [
                'total' => $incidents->count(),
                'open' => $incidents->whereNotIn('status', ['resolved', 'stood_down'])->count(),
                'resolved' => $incidents->where('status', 'resolved')->count(),
                'by_category' => $incidents->groupBy('category')->map->count(),
                'by_severity' => $incidents->groupBy('severity')->map->count(),
                'by_status' => $incidents->groupBy('status')->map->count(),
            ],
            'response_time_seconds' => [
                'measured' => count($responseSeconds),
                'average' => $responseSeconds === [] ? null : (int) round(array_sum($responseSeconds) / count($responseSeconds)),
                'median' => $this->median($responseSeconds),
            ],
            'sla_dispatch' => [
                'considered' => $slaConsidered,
                'met' => $slaMet,
                'compliance_pct' => $slaConsidered === 0 ? null : round($slaMet / $slaConsidered * 100, 1),
            ],
            'unit_utilization' => [
                'total' => $totalUnits,
                'assigned' => $assignedUnits,
                'pct' => $totalUnits === 0 ? null : round($assignedUnits / $totalUnits * 100, 1),
            ],
        ]]);
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? (int) round(($values[$mid - 1] + $values[$mid]) / 2)
            : (int) $values[$mid];
    }

    // ---- Taskings ------------------------------------------------------------

    public function routeTasking(RouteTaskingRequest $request): JsonResponse
    {
        $tasking = $this->coordination->routeTasking(
            RouteTaskingData::fromRequest($request),
            $request->user()->getKey(),
        );

        return TaskingResource::make($tasking)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function listTaskings(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $taskings = Tasking::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return TaskingResource::collection($taskings);
    }

    public function acknowledgeTasking(Request $request, Tasking $tasking): TaskingResource
    {
        $this->assertSuperAdmin($request);

        return TaskingResource::make($this->coordination->acknowledgeTasking($tasking));
    }

    public function resolveTasking(Request $request, Tasking $tasking): TaskingResource
    {
        $this->assertSuperAdmin($request);

        return TaskingResource::make($this->coordination->resolveTasking($tasking));
    }

    // ---- Duty book -----------------------------------------------------------

    public function recordDuty(RecordDutyRequest $request): JsonResponse
    {
        $entry = $this->coordination->recordDuty(RecordDutyData::fromRequest($request));

        return DutyEntryResource::make($entry)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function listDuty(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $entries = DutyEntry::query()
            ->when($request->filled('scope_type'), fn ($q) => $q->where('scope_type', $request->string('scope_type')->value()))
            ->latest('recorded_at')
            ->paginate($this->perPage($request));

        return DutyEntryResource::collection($entries);
    }
}
