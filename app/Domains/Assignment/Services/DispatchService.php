<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Services;

use App\Domains\Assignment\Events\AssignmentCancelled;
use App\Domains\Assignment\Events\AssignmentCompleted;
use App\Domains\Assignment\Events\ResponderAcceptedAssignment;
use App\Domains\Assignment\Events\ResponderAssignmentCompleted;
use App\Domains\Assignment\Events\ResponderAssignmentTimedOut;
use App\Domains\Assignment\Events\ResponderDeclinedAssignment;
use App\Domains\Assignment\Events\ResponderEnRoute;
use App\Domains\Assignment\Events\ResponderOffered;
use App\Domains\Assignment\Events\ResponderOnScene;
use App\Domains\Assignment\Events\ResponderStoodDown;
use App\Domains\Assignment\Jobs\ExpireAssignmentOffer;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Incident\Models\Incident;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Services\ResponderService;
use App\Domains\Responder\Support\Enums\ResponderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the per-responder dispatch lifecycle on an assignment: offering a role,
 * the responder's accept/decline, the en-route/on-scene progression, timeout,
 * stand-down, and assignment cancellation/completion. Every transition is
 * validated against the AssignmentResponderStatus graph, run under a row lock in
 * a transaction, emits a broadcast + audited event, appends to the timeline, and
 * re-derives the aggregate status.
 */
final readonly class DispatchService
{
    public function __construct(
        private AssignmentService $assignments,
        private ResponderService $responders,
    ) {}

    public function offer(Assignment $assignment, Responder $responder, ResponderRole $role, ?User $assignedBy): AssignmentResponder
    {
        return DB::transaction(function () use ($assignment, $responder, $role, $assignedBy): AssignmentResponder {
            $lockedResponder = Responder::query()->whereKey($responder->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedResponder->status->isAssignable()) {
                throw ValidationException::withMessages([
                    'responder' => ['The responder is not available for assignment.'],
                ]);
            }

            if ($this->responderHasActiveLine($lockedResponder)) {
                throw ValidationException::withMessages([
                    'responder' => ['The responder already has an active assignment.'],
                ]);
            }

            if ($role === ResponderRole::Primary && $this->assignmentHasActivePrimary($assignment)) {
                throw ValidationException::withMessages([
                    'role' => ['This assignment already has an active primary responder.'],
                ]);
            }

            // Attempt number = how many times this role has been offered on this
            // assignment so far + 1 (so reassignment after a decline/timeout shows
            // a rising attempt count).
            $attempt = AssignmentResponder::query()
                ->where('assignment_id', $assignment->getKey())
                ->where('role', $role->value)
                ->count() + 1;

            $line = AssignmentResponder::create([
                'assignment_id' => $assignment->getKey(),
                'organization_id' => $assignment->organization_id,
                'responder_id' => $lockedResponder->getKey(),
                'incident_id' => $assignment->incident_id,
                'role' => $role,
                'status' => AssignmentResponderStatus::Offered,
                'attempt' => $attempt,
                'assigned_by' => $assignedBy?->getKey(),
                'offered_at' => now(),
            ]);

            $context = ['responder_id' => $lockedResponder->getKey(), 'role' => $role->value, 'incident_id' => $assignment->incident_id];
            event(new ResponderOffered($line, $assignedBy?->getKey(), $context));
            $this->assignments->recordEvent($assignment, 'assignment.responder_offered', $assignedBy?->getKey(), $line->getKey(), $context);

            $timeout = (int) config('sentrix.responders.assignment_acceptance_timeout_seconds', 120);
            if ($timeout > 0) {
                ExpireAssignmentOffer::dispatch($line->getKey())->delay(now()->addSeconds($timeout));
            }

            $this->assignments->recompute($assignment);

            return $line;
        });
    }

    public function accept(AssignmentResponder $line, User $actor): AssignmentResponder
    {
        return DB::transaction(function () use ($line, $actor): AssignmentResponder {
            $locked = $this->lockLine($line);
            $this->guard($locked, AssignmentResponderStatus::Accepted);

            $locked->update(['status' => AssignmentResponderStatus::Accepted, 'accepted_at' => now()]);

            $responder = Responder::query()->whereKey($locked->responder_id)->lockForUpdate()->firstOrFail();
            $this->responders->transition($responder, ResponderStatus::Engaged, $actor);
            $responder->forceFill(['current_assignment_id' => $locked->getKey()])->save();

            $assignment = $locked->assignment()->lockForUpdate()->firstOrFail();

            if ($locked->role === ResponderRole::Primary) {
                $assignment->forceFill(['primary_assignment_responder_id' => $locked->getKey()])->save();
                Incident::query()->whereKey($assignment->incident_id)->update(['assigned_to' => $responder->user_id]);
            }

            $context = ['responder_id' => $responder->getKey(), 'role' => $locked->role->value];
            event(new ResponderAcceptedAssignment($locked, $actor->getKey(), $context));
            $this->assignments->recordEvent($assignment, 'assignment.responder_accepted', $actor->getKey(), $locked->getKey(), $context);

            $this->assignments->recompute($assignment);

            return $locked;
        });
    }

    public function decline(AssignmentResponder $line, User $actor, ?string $reason = null): AssignmentResponder
    {
        return DB::transaction(function () use ($line, $actor, $reason): AssignmentResponder {
            $locked = $this->lockLine($line);
            $this->guard($locked, AssignmentResponderStatus::Declined);

            $locked->update([
                'status' => AssignmentResponderStatus::Declined,
                'decline_reason' => $reason,
                'released_at' => now(),
            ]);

            $assignment = $locked->assignment()->firstOrFail();
            $context = ['responder_id' => $locked->responder_id, 'reason' => $reason];
            event(new ResponderDeclinedAssignment($locked, $actor->getKey(), $context));
            $this->assignments->recordEvent($assignment, 'assignment.responder_declined', $actor->getKey(), $locked->getKey(), $context);

            $this->assignments->recompute($assignment);

            return $locked;
        });
    }

    /**
     * Expire an unaccepted offer (from the timeout job). No-op if already actioned.
     */
    public function timeout(string $lineId): void
    {
        DB::transaction(function () use ($lineId): void {
            $locked = AssignmentResponder::query()->whereKey($lineId)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== AssignmentResponderStatus::Offered) {
                return;
            }

            $locked->update(['status' => AssignmentResponderStatus::TimedOut, 'released_at' => now()]);

            $assignment = $locked->assignment()->firstOrFail();
            $context = ['responder_id' => $locked->responder_id, 'role' => $locked->role->value];
            event(new ResponderAssignmentTimedOut($locked, null, $context));
            $this->assignments->recordEvent($assignment, 'assignment.responder_timed_out', null, $locked->getKey(), $context);

            $this->assignments->recompute($assignment);
        });
    }

    public function markEnRoute(AssignmentResponder $line, User $actor): AssignmentResponder
    {
        return $this->advance($line, AssignmentResponderStatus::EnRoute, 'en_route_at', $actor);
    }

    public function markOnScene(AssignmentResponder $line, User $actor): AssignmentResponder
    {
        return $this->advance($line, AssignmentResponderStatus::OnScene, 'on_scene_at', $actor);
    }

    public function completeLine(AssignmentResponder $line, User $actor, ?string $outcome = null): AssignmentResponder
    {
        return DB::transaction(function () use ($line, $actor, $outcome): AssignmentResponder {
            $locked = $this->lockLine($line);
            $this->guard($locked, AssignmentResponderStatus::Completed);

            $locked->update(['status' => AssignmentResponderStatus::Completed, 'completed_at' => now(), 'outcome' => $outcome]);

            $assignment = $locked->assignment()->lockForUpdate()->firstOrFail();
            $this->releaseResponder($locked, $assignment, $actor);

            $context = ['responder_id' => $locked->responder_id, 'outcome' => $outcome];
            event(new ResponderAssignmentCompleted($locked, $actor->getKey(), $context));
            $this->assignments->recordEvent($assignment, 'assignment.responder_completed', $actor->getKey(), $locked->getKey(), $context);
            $this->assignments->recompute($assignment);

            return $locked;
        });
    }

    public function cancelAssignment(Assignment $assignment, ?User $actor = null): Assignment
    {
        return DB::transaction(function () use ($assignment, $actor): Assignment {
            $locked = Assignment::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            $activeLines = $locked->responders()
                ->whereIn('status', AssignmentResponderStatus::activeValues())
                ->lockForUpdate()
                ->get();

            foreach ($activeLines as $line) {
                $this->standDown($line, $locked, $actor, 'cancelled');
            }

            $locked->update(['status' => AssignmentStatus::Cancelled, 'primary_assignment_responder_id' => null]);

            event(new AssignmentCancelled($locked, $actor?->getKey(), ['incident_id' => $locked->incident_id]));
            $this->assignments->recordEvent($locked, 'assignment.cancelled', $actor?->getKey(), null, []);

            return $locked;
        });
    }

    public function completeAssignment(Assignment $assignment, ?User $actor = null): Assignment
    {
        return DB::transaction(function () use ($assignment, $actor): Assignment {
            $locked = Assignment::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            // Release anyone still committed; their participation is complete.
            $committed = $locked->responders()
                ->whereIn('status', [AssignmentResponderStatus::Accepted->value, AssignmentResponderStatus::EnRoute->value, AssignmentResponderStatus::OnScene->value])
                ->lockForUpdate()
                ->get();

            foreach ($committed as $line) {
                $line->update(['status' => AssignmentResponderStatus::Completed, 'completed_at' => now()]);
                $this->releaseResponder($line, $locked, $actor);
            }

            $locked->update(['status' => AssignmentStatus::Completed]);

            event(new AssignmentCompleted($locked, $actor?->getKey(), ['incident_id' => $locked->incident_id]));
            $this->assignments->recordEvent($locked, 'assignment.completed', $actor?->getKey(), null, []);

            return $locked;
        });
    }

    /**
     * Stand a single active line down (reassignment / connectivity loss) and
     * re-derive the aggregate. Idempotent: a no-op if the line is no longer active.
     */
    public function standDownLine(AssignmentResponder $line, ?User $actor, string $reason): void
    {
        DB::transaction(function () use ($line, $actor, $reason): void {
            $locked = $this->lockLine($line);

            if (! $locked->status->isActive()) {
                return;
            }

            $assignment = $locked->assignment()->lockForUpdate()->firstOrFail();
            $this->standDown($locked, $assignment, $actor, $reason);
            $this->assignments->recompute($assignment);
        });
    }

    private function advance(AssignmentResponder $line, AssignmentResponderStatus $target, string $timestampColumn, User $actor): AssignmentResponder
    {
        return DB::transaction(function () use ($line, $target, $timestampColumn, $actor): AssignmentResponder {
            $locked = $this->lockLine($line);
            $this->guard($locked, $target);

            $locked->update(['status' => $target, $timestampColumn => now()]);

            $assignment = $locked->assignment()->firstOrFail();
            $context = ['responder_id' => $locked->responder_id, 'role' => $locked->role->value];

            $event = match ($target) {
                AssignmentResponderStatus::EnRoute => new ResponderEnRoute($locked, $actor->getKey(), $context),
                AssignmentResponderStatus::OnScene => new ResponderOnScene($locked, $actor->getKey(), $context),
                default => null,
            };

            if ($event !== null) {
                event($event);
            }

            $this->assignments->recordEvent($assignment, "assignment.responder_{$target->value}", $actor->getKey(), $locked->getKey(), $context);
            $this->assignments->recompute($assignment);

            return $locked;
        });
    }

    private function standDown(AssignmentResponder $line, Assignment $assignment, ?User $actor, string $reason): void
    {
        $line->update(['status' => AssignmentResponderStatus::StoodDown, 'released_at' => now(), 'decline_reason' => $reason]);

        $this->releaseResponder($line, $assignment, $actor);

        $context = ['responder_id' => $line->responder_id, 'reason' => $reason];
        event(new ResponderStoodDown($line, $actor?->getKey(), $context));
        $this->assignments->recordEvent($assignment, 'assignment.responder_stood_down', $actor?->getKey(), $line->getKey(), $context);
    }

    /**
     * Release the responder back to availability, clear the current-assignment
     * pointer, and (for the primary) clear the assignment's primary pointer and
     * the incident's assignee.
     */
    private function releaseResponder(AssignmentResponder $line, Assignment $assignment, ?User $actor): void
    {
        $responder = Responder::query()->whereKey($line->responder_id)->lockForUpdate()->first();

        if ($responder !== null) {
            if ($responder->status === ResponderStatus::Engaged) {
                $this->responders->transition($responder, ResponderStatus::Available, $actor);
            }

            if ($responder->current_assignment_id === $line->getKey()) {
                $responder->forceFill(['current_assignment_id' => null])->save();
            }

            if ($line->role === ResponderRole::Primary) {
                if ($assignment->primary_assignment_responder_id === $line->getKey()) {
                    $assignment->forceFill(['primary_assignment_responder_id' => null])->save();
                }

                Incident::query()
                    ->whereKey($assignment->incident_id)
                    ->where('assigned_to', $responder->user_id)
                    ->update(['assigned_to' => null]);
            }
        }
    }

    private function lockLine(AssignmentResponder $line): AssignmentResponder
    {
        return AssignmentResponder::query()->whereKey($line->getKey())->lockForUpdate()->firstOrFail();
    }

    private function guard(AssignmentResponder $line, AssignmentResponderStatus $target): void
    {
        if (! $line->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ["An assignment line cannot move from {$line->status->value} to {$target->value}."],
            ]);
        }
    }

    private function responderHasActiveLine(Responder $responder): bool
    {
        return AssignmentResponder::query()
            ->where('responder_id', $responder->getKey())
            ->whereIn('status', AssignmentResponderStatus::activeValues())
            ->exists();
    }

    private function assignmentHasActivePrimary(Assignment $assignment): bool
    {
        return AssignmentResponder::query()
            ->where('assignment_id', $assignment->getKey())
            ->where('role', ResponderRole::Primary->value)
            ->whereIn('status', AssignmentResponderStatus::activeValues())
            ->exists();
    }
}
