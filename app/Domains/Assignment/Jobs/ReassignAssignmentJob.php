<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Jobs;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Services\DispatchRecommender;
use App\Domains\Assignment\Services\DispatchService;
use App\Domains\Assignment\Services\EscalationService;
use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Responder\Models\Responder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Re-offers a still-needed role to the next-best candidate after a decline,
 * timeout, or stand-down. If the role is already filled, no longer needed, or the
 * attempt budget is exhausted / no candidates remain, it escalates instead.
 * Idempotent: re-checks need against current state before acting.
 */
final class ReassignAssignmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $assignmentId,
        public readonly string $role,
    ) {
        $this->onQueue('critical');
    }

    public function handle(DispatchService $dispatch, DispatchRecommender $recommender, EscalationService $escalation): void
    {
        $assignment = Assignment::query()->with('incident')->find($this->assignmentId);

        if ($assignment === null || $assignment->status->isTerminal()) {
            return;
        }

        $role = ResponderRole::from($this->role);

        if (! $this->roleStillNeeded($assignment, $role)) {
            return;
        }

        $maxAttempts = (int) config('sentrix.assignments.max_reassign_attempts', 3);
        $attemptsSoFar = AssignmentResponder::query()
            ->where('assignment_id', $assignment->getKey())
            ->where('role', $role->value)
            ->count();

        if ($attemptsSoFar >= $maxAttempts) {
            $escalation->escalate($assignment, 'max_reassign_attempts');

            return;
        }

        $excluded = AssignmentResponder::query()
            ->where('assignment_id', $assignment->getKey())
            ->pluck('responder_id')
            ->all();

        $incident = $assignment->incident;
        $candidates = $recommender->recommend(
            (string) $assignment->organization_id,
            $incident?->getAttribute('lat') !== null ? (float) $incident->getAttribute('lat') : null,
            $incident?->getAttribute('lng') !== null ? (float) $incident->getAttribute('lng') : null,
            (int) config('sentrix.responders.ai_dispatch_shortlist_size', 5),
            $excluded,
        );

        foreach ($candidates as $candidate) {
            $responder = Responder::find($candidate['responder_id']);

            if ($responder === null) {
                continue;
            }

            try {
                $dispatch->offer($assignment, $responder, $role, null);

                return; // Successfully re-offered.
            } catch (ValidationException) {
                continue; // Candidate became unavailable; try the next.
            }
        }

        // No candidate could be offered.
        $escalation->escalate($assignment, 'no_candidates');
    }

    private function roleStillNeeded(Assignment $assignment, ResponderRole $role): bool
    {
        $lines = $assignment->responders()->get();

        if ($role === ResponderRole::Primary) {
            return ! $lines->contains(
                fn ($line): bool => $line->role === ResponderRole::Primary && $line->status->isActive(),
            );
        }

        $supportingActive = $lines
            ->filter(fn ($line): bool => $line->role === ResponderRole::Supporting && $line->status->isActive())
            ->count();

        return $supportingActive < $assignment->required_supporting;
    }
}
