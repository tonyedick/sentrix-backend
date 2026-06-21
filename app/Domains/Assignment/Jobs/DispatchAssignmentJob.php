<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Jobs;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Services\DispatchRecommender;
use App\Domains\Assignment\Services\DispatchService;
use App\Domains\Assignment\Services\EscalationService;
use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Responder\Models\Responder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Auto-dispatch for an assignment opened with dispatch_mode=auto: offers the
 * recommended primary and enough supporting responders to meet the required
 * composition. Idempotent — re-checks current need and only offers the shortfall.
 * Escalates if the primary cannot be offered to anyone.
 */
final class DispatchAssignmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $assignmentId)
    {
        $this->onQueue('critical');
    }

    public function handle(DispatchService $dispatch, DispatchRecommender $recommender, EscalationService $escalation): void
    {
        $assignment = Assignment::query()->with('incident')->find($this->assignmentId);

        if ($assignment === null || $assignment->status->isTerminal() || $assignment->dispatch_mode !== 'auto') {
            return;
        }

        $lines = $assignment->responders()->get();
        $needPrimary = $assignment->required_primary
            && ! $lines->contains(fn ($l): bool => $l->role === ResponderRole::Primary && $l->status->isActive());
        $supportingActive = $lines->filter(fn ($l): bool => $l->role === ResponderRole::Supporting && $l->status->isActive())->count();
        $needSupporting = max(0, $assignment->required_supporting - $supportingActive);

        if (! $needPrimary && $needSupporting === 0) {
            return;
        }

        $excluded = $lines->pluck('responder_id')->all();
        $incident = $assignment->incident;
        $candidates = $recommender->recommend(
            (string) $assignment->organization_id,
            $incident?->getAttribute('lat') !== null ? (float) $incident->getAttribute('lat') : null,
            $incident?->getAttribute('lng') !== null ? (float) $incident->getAttribute('lng') : null,
            $needSupporting + 1 + ($excluded === [] ? 0 : count($excluded)),
            $excluded,
        );

        $primaryOffered = ! $needPrimary;

        foreach ($candidates as $candidate) {
            $responder = Responder::find($candidate['responder_id']);
            if ($responder === null) {
                continue;
            }

            $role = $primaryOffered ? ResponderRole::Supporting : ResponderRole::Primary;

            try {
                $dispatch->offer($assignment, $responder, $role, null);
            } catch (ValidationException) {
                continue;
            }

            if ($role === ResponderRole::Primary) {
                $primaryOffered = true;
            } else {
                $needSupporting--;
            }

            if ($primaryOffered && $needSupporting <= 0) {
                return;
            }
        }

        if ($needPrimary && ! $primaryOffered) {
            $escalation->escalate($assignment, 'no_candidates');
        }
    }
}
