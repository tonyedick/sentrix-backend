<?php

declare(strict_types=1);

namespace App\Domains\Incident\Support;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentEvent;
use App\Domains\Incident\Models\Incident;
use Carbon\CarbonInterface;

/**
 * Reconstructs an incident's timeline from the legacy sources — the incident's
 * milestone timestamps plus the Assignment domain's assignment_events.
 *
 * This is the derivation logic that USED to power the live read path. It now
 * lives off the read path: it is used only (a) by the one-time backfill command
 * to populate incident_timeline_entries for pre-existing incidents, and (b) by
 * the parity test to verify the projector matches what derivation would produce.
 * The live API reads incident_timeline_entries exclusively (IncidentTimelineService).
 */
final readonly class IncidentTimelineDeriver
{
    /**
     * @return list<array{occurred_at: CarbonInterface, type: string, source: string, actor_id: string|null, payload: array<string, mixed>}>
     */
    public function derive(Incident $incident): array
    {
        $entries = [];

        foreach ([
            'incident.opened' => $incident->opened_at,
            'incident.escalated' => $incident->escalated_at,
            'incident.resolved' => $incident->resolved_at,
            'incident.closed' => $incident->closed_at,
        ] as $type => $at) {
            if ($at instanceof CarbonInterface) {
                $entries[] = [
                    'occurred_at' => $at,
                    'type' => $type,
                    'source' => 'incident',
                    'actor_id' => null,
                    'payload' => ['status' => $incident->status->value],
                ];
            }
        }

        $assignmentIds = Assignment::query()
            ->where('incident_id', $incident->getKey())
            ->pluck('id');

        if ($assignmentIds->isNotEmpty()) {
            AssignmentEvent::query()
                ->whereIn('assignment_id', $assignmentIds)
                ->orderBy('created_at')
                ->get()
                ->each(function (AssignmentEvent $event) use (&$entries): void {
                    if ($event->created_at === null) {
                        return;
                    }

                    $entries[] = [
                        'occurred_at' => $event->created_at,
                        'type' => $event->type,
                        'source' => 'assignment',
                        'actor_id' => $event->actor_id,
                        'payload' => $event->payload ?? [],
                    ];
                });
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => $a['occurred_at']->getTimestamp() <=> $b['occurred_at']->getTimestamp(),
        );

        return $entries;
    }
}
