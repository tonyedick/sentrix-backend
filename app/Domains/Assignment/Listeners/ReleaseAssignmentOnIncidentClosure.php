<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Listeners;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Services\DispatchService;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Domains\Incident\Models\Incident;
use App\Domains\Shared\Events\OrganizationRecordEvent;
use App\Domains\Shared\Listeners\QueuedListener;
use App\Models\User;

/**
 * Closes the operational loop (operational-event-map §10.1): when an incident is
 * resolved or closed, its active assignment is completed and every engaged
 * responder released. Without this, responders stay `engaged` forever after an
 * incident ends. Idempotent — only an active assignment is acted on, so a
 * resolve-then-close sequence runs the work exactly once.
 */
final class ReleaseAssignmentOnIncidentClosure extends QueuedListener
{
    public function __construct(private readonly DispatchService $dispatch) {}

    public function handle(OrganizationRecordEvent $event): void
    {
        $incident = $event->record;

        if (! $incident instanceof Incident) {
            return;
        }

        $assignment = Assignment::query()
            ->where('incident_id', $incident->getKey())
            ->whereNotIn('status', [AssignmentStatus::Completed->value, AssignmentStatus::Cancelled->value])
            ->first();

        if ($assignment === null) {
            return;
        }

        $actor = $event->actorId !== null ? User::find($event->actorId) : null;

        $this->dispatch->completeAssignment($assignment, $actor);
    }
}
