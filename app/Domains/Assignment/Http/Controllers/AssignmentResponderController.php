<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Http\Controllers;

use App\Domains\Assignment\Http\Resources\AssignmentResponderResource;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Services\DispatchService;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-responder actions on an assignment line. The assigned responder may action
 * their own line (responders.self); a dispatcher may act on anyone's
 * (assignments.dispatch).
 */
final class AssignmentResponderController extends Controller
{
    public function __construct(private readonly DispatchService $dispatch) {}

    public function accept(Request $request, Organization $organization, Assignment $assignment, AssignmentResponder $line): AssignmentResponderResource
    {
        $this->authorizeLine($request->user(), $organization, $assignment, $line);

        return AssignmentResponderResource::make($this->dispatch->accept($line, $request->user()));
    }

    public function decline(Request $request, Organization $organization, Assignment $assignment, AssignmentResponder $line): AssignmentResponderResource
    {
        $this->authorizeLine($request->user(), $organization, $assignment, $line);

        return AssignmentResponderResource::make(
            $this->dispatch->decline($line, $request->user(), $request->input('reason')),
        );
    }

    public function enRoute(Request $request, Organization $organization, Assignment $assignment, AssignmentResponder $line): AssignmentResponderResource
    {
        $this->authorizeLine($request->user(), $organization, $assignment, $line);

        return AssignmentResponderResource::make($this->dispatch->markEnRoute($line, $request->user()));
    }

    public function onScene(Request $request, Organization $organization, Assignment $assignment, AssignmentResponder $line): AssignmentResponderResource
    {
        $this->authorizeLine($request->user(), $organization, $assignment, $line);

        return AssignmentResponderResource::make($this->dispatch->markOnScene($line, $request->user()));
    }

    public function complete(Request $request, Organization $organization, Assignment $assignment, AssignmentResponder $line): AssignmentResponderResource
    {
        $this->authorizeLine($request->user(), $organization, $assignment, $line);

        return AssignmentResponderResource::make(
            $this->dispatch->completeLine($line, $request->user(), $request->input('outcome')),
        );
    }

    /**
     * Self-service on one's own line (responders.self) or a dispatcher action
     * (assignments.dispatch). Also enforces org + parent scoping.
     */
    private function authorizeLine(User $actor, Organization $organization, Assignment $assignment, AssignmentResponder $line): void
    {
        abort_if($assignment->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
        abort_if($line->assignment_id !== $assignment->getKey(), Response::HTTP_NOT_FOUND);

        $line->loadMissing('responder');
        $isSelf = $line->responder?->user_id === $actor->getKey();

        if ($isSelf) {
            abort_unless($actor->can(DefaultPermission::RespondersSelf->value), Response::HTTP_FORBIDDEN);

            return;
        }

        abort_unless($actor->can(DefaultPermission::AssignmentsDispatch->value), Response::HTTP_FORBIDDEN);
    }
}
