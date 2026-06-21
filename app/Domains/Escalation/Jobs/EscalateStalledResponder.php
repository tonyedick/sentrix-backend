<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Jobs;

use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Services\EscalationService;
use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Responder escalation: dispatched (delayed by the org's no-progression
 * threshold) when a responder accepts. If, when it runs, the line is still merely
 * `accepted` (never advanced to en-route/on-scene and not ended), the assignment
 * is escalated for dispatcher attention. Idempotent — a no-op if the responder
 * progressed, the line ended, or the assignment is already escalated/terminal.
 */
final class EscalateStalledResponder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $assignmentResponderId)
    {
        $this->onQueue('escalation');
    }

    public function handle(EscalationService $escalation): void
    {
        $line = AssignmentResponder::with('assignment')->find($this->assignmentResponderId);

        if ($line === null || $line->status !== AssignmentResponderStatus::Accepted) {
            return; // Progressed, declined, completed, stood down — nothing stalled.
        }

        $assignment = $line->assignment;

        if ($assignment === null
            || $assignment->status->isTerminal()
            || $assignment->status === AssignmentStatus::Escalated) {
            return;
        }

        $escalation->escalate($assignment, 'responder_no_progression');
    }
}
