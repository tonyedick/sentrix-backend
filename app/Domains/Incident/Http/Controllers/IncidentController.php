<?php

declare(strict_types=1);

namespace App\Domains\Incident\Http\Controllers;

use App\Domains\Assignment\Http\Resources\AssignmentResource;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\DTOs\OpenIncidentData;
use App\Domains\Incident\DTOs\UpdateIncidentData;
use App\Domains\Incident\Http\Requests\OpenIncidentRequest;
use App\Domains\Incident\Http\Requests\UpdateIncidentRequest;
use App\Domains\Incident\Http\Resources\IncidentResource;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Services\IncidentService;
use App\Domains\Incident\Services\IncidentTimelineService;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped incident management with explicit workflow transitions.
 */
final class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::IncidentsView->value),
            Response::HTTP_FORBIDDEN,
        );

        $incidents = Incident::query()
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->string('severity')->value()))
            ->latest('opened_at')
            ->paginate($this->perPage($request));

        return IncidentResource::collection($incidents);
    }

    public function store(OpenIncidentRequest $request, Organization $organization): JsonResponse
    {
        if ($request->filled('emergency_id')) {
            abort_unless(
                Emergency::whereKey($request->string('emergency_id')->value())
                    ->where('organization_id', $organization->getKey())
                    ->exists(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The referenced emergency does not belong to this organization.',
            );
        }

        $this->assertAssigneeIsMember($request, $organization);

        $incident = $this->incidents->open(
            $organization,
            OpenIncidentData::fromRequest($request),
            $request->user(),
        );

        return IncidentResource::make($incident)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Operational incident detail: the incident, its active assignment (with
     * responder lines), and a consolidated timeline.
     */
    public function show(Request $request, Organization $organization, Incident $incident, IncidentTimelineService $timeline): JsonResponse
    {
        $this->assertIncidentInOrganization($organization, $incident);
        abort_unless($request->user()->can(DefaultPermission::IncidentsView->value), Response::HTTP_FORBIDDEN);

        $assignment = $this->activeAssignment($incident);

        return response()->json([
            'data' => [
                'incident' => IncidentResource::make($incident)->resolve($request),
                'assignment' => $assignment !== null
                    ? AssignmentResource::make($assignment->load('responders'))->resolve($request)
                    : null,
                'timeline' => $timeline->forIncident($incident),
            ],
        ]);
    }

    public function timeline(Request $request, Organization $organization, Incident $incident, IncidentTimelineService $timeline): JsonResponse
    {
        $this->assertIncidentInOrganization($organization, $incident);
        abort_unless($request->user()->can(DefaultPermission::IncidentsView->value), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $timeline->forIncident($incident)]);
    }

    private function activeAssignment(Incident $incident): ?Assignment
    {
        return Assignment::query()
            ->where('incident_id', $incident->getKey())
            ->whereNotIn('status', [AssignmentStatus::Completed->value, AssignmentStatus::Cancelled->value])
            ->latest('created_at')
            ->first();
    }

    public function update(UpdateIncidentRequest $request, Organization $organization, Incident $incident): IncidentResource
    {
        $this->assertIncidentInOrganization($organization, $incident);
        $this->assertAssigneeIsMember($request, $organization);

        return IncidentResource::make(
            $this->incidents->updateDetails($incident, UpdateIncidentData::fromRequest($request)),
        );
    }

    public function investigate(Request $request, Organization $organization, Incident $incident): IncidentResource
    {
        $this->assertIncidentInOrganization($organization, $incident);
        abort_unless($request->user()->can(DefaultPermission::IncidentsUpdate->value), Response::HTTP_FORBIDDEN);

        return IncidentResource::make($this->incidents->startInvestigation($incident, $request->user()));
    }

    public function escalate(Request $request, Organization $organization, Incident $incident): IncidentResource
    {
        $this->assertIncidentInOrganization($organization, $incident);
        abort_unless($request->user()->can(DefaultPermission::IncidentsEscalate->value), Response::HTTP_FORBIDDEN);

        return IncidentResource::make($this->incidents->escalate($incident, $request->user()));
    }

    public function resolve(Request $request, Organization $organization, Incident $incident): IncidentResource
    {
        $this->assertIncidentInOrganization($organization, $incident);
        abort_unless($request->user()->can(DefaultPermission::IncidentsResolve->value), Response::HTTP_FORBIDDEN);

        return IncidentResource::make($this->incidents->resolve($incident, $request->user()));
    }

    public function close(Request $request, Organization $organization, Incident $incident): IncidentResource
    {
        $this->assertIncidentInOrganization($organization, $incident);
        abort_unless($request->user()->can(DefaultPermission::IncidentsResolve->value), Response::HTTP_FORBIDDEN);

        return IncidentResource::make($this->incidents->close($incident, $request->user()));
    }

    private function assertAssigneeIsMember(Request $request, Organization $organization): void
    {
        if ($request->filled('assigned_to')) {
            abort_unless(
                User::whereKey($request->string('assigned_to')->value())->first()?->belongsToOrganization($organization) === true,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The assignee is not a member of this organization.',
            );
        }
    }

    private function assertIncidentInOrganization(Organization $organization, Incident $incident): void
    {
        abort_if($incident->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
