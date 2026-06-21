<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Http\Controllers;

use App\Domains\Assignment\Http\Requests\OfferResponderRequest;
use App\Domains\Assignment\Http\Requests\OpenAssignmentRequest;
use App\Domains\Assignment\Http\Resources\AssignmentResource;
use App\Domains\Assignment\Http\Resources\AssignmentResponderResource;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Services\AssignmentService;
use App\Domains\Assignment\Services\DispatchService;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Incident\Models\Incident;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Models\Responder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The assignment aggregate: opening a coordination record for an incident,
 * offering responders (manual dispatch), and cancel/complete. Per-responder
 * accept/decline/progress live in AssignmentResponderController.
 */
final class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly DispatchService $dispatch,
    ) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::AssignmentsView->value), Response::HTTP_FORBIDDEN);

        $assignments = Assignment::query()
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('incident_id'), fn ($query) => $query->where('incident_id', $request->string('incident_id')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return AssignmentResource::collection($assignments);
    }

    public function store(OpenAssignmentRequest $request, Organization $organization): JsonResponse
    {
        $incident = Incident::query()
            ->whereKey($request->string('incident_id')->value())
            ->where('organization_id', $organization->getKey())
            ->first();
        abort_if($incident === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'The incident does not belong to this organization.');

        $assignment = $this->assignments->open(
            $organization,
            $incident,
            $request->string('dispatch_mode', 'manual')->value(),
            (int) $request->integer('required_supporting', 0),
            $request->user(),
        );

        return AssignmentResource::make($assignment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Organization $organization, Assignment $assignment): AssignmentResource
    {
        $this->assertInOrganization($organization, $assignment);
        abort_unless($request->user()->can(DefaultPermission::AssignmentsView->value), Response::HTTP_FORBIDDEN);

        return AssignmentResource::make($assignment->load('responders'));
    }

    public function addResponder(OfferResponderRequest $request, Organization $organization, Assignment $assignment): JsonResponse
    {
        $this->assertInOrganization($organization, $assignment);

        $responder = Responder::query()
            ->whereKey($request->string('responder_id')->value())
            ->where('organization_id', $organization->getKey())
            ->first();
        abort_if($responder === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'The responder does not belong to this organization.');

        $role = ResponderRole::from($request->string('role', ResponderRole::Primary->value)->value());

        $line = $this->dispatch->offer($assignment, $responder, $role, $request->user());

        return AssignmentResponderResource::make($line)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function cancel(Request $request, Organization $organization, Assignment $assignment): AssignmentResource
    {
        $this->assertInOrganization($organization, $assignment);
        abort_unless($request->user()->can(DefaultPermission::AssignmentsCancel->value), Response::HTTP_FORBIDDEN);

        return AssignmentResource::make($this->dispatch->cancelAssignment($assignment, $request->user()));
    }

    public function complete(Request $request, Organization $organization, Assignment $assignment): AssignmentResource
    {
        $this->assertInOrganization($organization, $assignment);
        abort_unless($request->user()->can(DefaultPermission::AssignmentsDispatch->value), Response::HTTP_FORBIDDEN);

        return AssignmentResource::make($this->dispatch->completeAssignment($assignment, $request->user()));
    }

    private function assertInOrganization(Organization $organization, Assignment $assignment): void
    {
        abort_if($assignment->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
