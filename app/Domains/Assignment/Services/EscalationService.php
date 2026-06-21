<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Services;

use App\Domains\Assignment\Events\AssignmentDispatchEscalated;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Escalates an assignment whose dispatch could not be filled (deadline passed or
 * candidate pool exhausted). Bumps the escalation level, flags the aggregate as
 * dispatch-escalated, and emits an event that pages dispatchers. Idempotent per
 * tier: an already-escalated assignment is only re-escalated if the caller forces
 * a new tier (the deadline sweep filters escalated rows out, so it won't spam).
 */
final readonly class EscalationService
{
    public function escalate(Assignment $assignment, string $reason, ?User $actor = null): Assignment
    {
        return DB::transaction(function () use ($assignment, $reason, $actor): Assignment {
            $locked = Assignment::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            $level = $locked->escalation_level + 1;
            $locked->update([
                'status' => AssignmentStatus::Escalated,
                'escalation_level' => $level,
            ]);

            $context = ['reason' => $reason, 'level' => $level, 'incident_id' => $locked->incident_id];
            event(new AssignmentDispatchEscalated($locked, $actor?->getKey(), $context));
            $this->recordTimeline($locked, $actor?->getKey(), $context);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function recordTimeline(Assignment $assignment, ?string $actorId, array $context): void
    {
        app(AssignmentService::class)->recordEvent($assignment, 'assignment.dispatch_escalated', $actorId, null, $context);
    }
}
