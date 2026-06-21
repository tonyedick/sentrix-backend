<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Services;

use App\Domains\Assignment\Events\AssignmentCreated;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentEvent;
use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Incident\Models\Incident;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the assignment aggregate: opening the coordination record for an incident,
 * deriving its status from its responder lines, and appending to the operational
 * timeline. Deliberately free of responder-status coupling (that lives in
 * DispatchService) so the two services don't depend on each other circularly.
 */
final readonly class AssignmentService
{
    /**
     * Open (or return the existing active) assignment for an incident. One active
     * assignment per incident is also enforced by a partial unique index.
     */
    public function open(Organization $organization, Incident $incident, string $dispatchMode, int $requiredSupporting, ?User $opener): Assignment
    {
        return DB::transaction(function () use ($organization, $incident, $dispatchMode, $requiredSupporting, $opener): Assignment {
            $existing = Assignment::query()
                ->where('incident_id', $incident->getKey())
                ->whereNotIn('status', [AssignmentStatus::Completed->value, AssignmentStatus::Cancelled->value])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $timeout = (int) config('sentrix.responders.assignment_acceptance_timeout_seconds', 120);

            $assignment = Assignment::create([
                'organization_id' => $organization->getKey(),
                'incident_id' => $incident->getKey(),
                'status' => AssignmentStatus::Pending,
                'dispatch_mode' => $dispatchMode,
                'required_primary' => true,
                'required_supporting' => $requiredSupporting,
                'opened_by' => $opener?->getKey(),
                'acceptance_deadline_at' => $timeout > 0 ? now()->addSeconds($timeout) : null,
            ]);

            $this->recordEvent($assignment, 'assignment.created', $opener?->getKey(), null, [
                'dispatch_mode' => $dispatchMode,
                'required_supporting' => $requiredSupporting,
            ]);

            event(new AssignmentCreated($assignment, $opener?->getKey(), [
                'incident_id' => $incident->getKey(),
                'dispatch_mode' => $dispatchMode,
            ]));

            return $assignment;
        });
    }

    /**
     * Recompute the aggregate status from its lines + required composition.
     * `completed`/`cancelled` are terminal and set explicitly elsewhere.
     */
    public function recompute(Assignment $assignment): Assignment
    {
        // Read current DB state — another path (e.g. a synchronous reassignment
        // → escalation triggered by an event we just emitted) may have moved the
        // status out from under our in-memory instance.
        $assignment->refresh();

        if ($assignment->status->isTerminal()) {
            return $assignment;
        }

        $lines = $assignment->responders()->get();

        $primaryCommitted = $lines->first(
            fn ($line): bool => $line->role === ResponderRole::Primary && $line->status->isCommitted(),
        );
        $supportingCommitted = $lines
            ->filter(fn ($line): bool => $line->role === ResponderRole::Supporting && $line->status->isCommitted())
            ->count();
        $anyCommitted = $lines->contains(fn ($line): bool => $line->status->isCommitted());
        $anyOffered = $lines->contains(fn ($line): bool => $line->status === AssignmentResponderStatus::Offered);

        $primaryOk = ! $assignment->required_primary || $primaryCommitted !== null;
        $supportingOk = $supportingCommitted >= $assignment->required_supporting;

        if ($primaryOk && $supportingOk) {
            $advanced = $primaryCommitted !== null
                && in_array($primaryCommitted->status, [AssignmentResponderStatus::EnRoute, AssignmentResponderStatus::OnScene], true);
            $status = $advanced ? AssignmentStatus::Active : AssignmentStatus::Filled;
        } elseif ($anyCommitted) {
            $status = AssignmentStatus::PartiallyFilled;
        } elseif ($anyOffered) {
            $status = AssignmentStatus::Dispatching;
        } else {
            $status = AssignmentStatus::Pending;
        }

        // Escalation is sticky: once a dispatch is escalated, stay escalated until
        // it is actually filled (or terminated) — don't silently un-escalate just
        // because a line changed (e.g. the declining line that triggered escalation).
        if ($assignment->status === AssignmentStatus::Escalated
            && ! in_array($status, [AssignmentStatus::Filled, AssignmentStatus::Active], true)) {
            return $assignment;
        }

        if ($status !== $assignment->status) {
            $assignment->update(['status' => $status]);
        }

        return $assignment;
    }

    /**
     * Append an immutable row to the assignment's operational timeline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(Assignment $assignment, string $type, ?string $actorId, ?string $lineId, array $payload = []): void
    {
        AssignmentEvent::create([
            'assignment_id' => $assignment->getKey(),
            'organization_id' => $assignment->organization_id,
            'type' => $type,
            'actor_id' => $actorId,
            'assignment_responder_id' => $lineId,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }
}
