<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Jobs;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Services\IncidentService;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Incident escalation: dispatched (delayed by the org's incident_unassigned
 * threshold) when an incident is opened. If, when it runs, the incident is still
 * open/investigating and has NO assignment, it is escalated. Idempotent — a
 * no-op if the incident was assigned, already escalated, or resolved/closed.
 */
final class EscalateUnassignedIncident implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $incidentId)
    {
        $this->onQueue('escalation');
    }

    public function handle(IncidentService $incidents): void
    {
        $incident = Incident::find($this->incidentId);

        if ($incident === null) {
            return;
        }

        // Only escalate from a pre-escalation, non-terminal state.
        if (! in_array($incident->status, [IncidentStatus::Open, IncidentStatus::Investigating], true)) {
            return;
        }

        $hasAssignment = Assignment::query()
            ->where('incident_id', $incident->getKey())
            ->whereNotIn('status', [AssignmentStatus::Completed->value, AssignmentStatus::Cancelled->value])
            ->exists();

        if ($hasAssignment) {
            return; // Assigned within the window — nothing to escalate.
        }

        $incidents->escalate($incident); // system actor (null) → IncidentEscalated
    }
}
