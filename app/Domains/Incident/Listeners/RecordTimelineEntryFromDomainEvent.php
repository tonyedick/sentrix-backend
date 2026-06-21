<?php

declare(strict_types=1);

namespace App\Domains\Incident\Listeners;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Services\IncidentTimelineRecorder;
use App\Domains\Shared\Events\OrganizationRecordEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Projects operational domain events onto the incident timeline by appending an
 * incident_timeline_entries row via the recorder.
 *
 * Intentionally SYNCHRONOUS (not queued), mirroring the audit trail: the timeline
 * entry is written in the same transaction as the domain change it records, so it
 * is atomic with that change (rolls back together) and runs exactly once — no
 * at-least-once queue delivery to dedupe against. Heavy/async fan-out (realtime,
 * AI) hangs off the lightweight TimelineEntryRecorded event the recorder emits,
 * not off this projection.
 *
 * Registered for the events that belong on an incident's timeline; an event whose
 * record can't be resolved to an incident is skipped.
 */
final class RecordTimelineEntryFromDomainEvent
{
    public function __construct(private readonly IncidentTimelineRecorder $recorder) {}

    public function handle(OrganizationRecordEvent $event): void
    {
        $record = $event->record;
        [$incidentId, $source] = $this->locate($record);

        if ($incidentId === null) {
            return;
        }

        $this->recorder->record(
            organizationId: (string) $record->getAttribute('organization_id'),
            incidentId: $incidentId,
            type: $event->action(),
            source: $source,
            actorId: $event->actorId,
            subject: $record,
            payload: $event->context,
        );
    }

    /**
     * Resolve the owning incident id and the producing source for an event's record.
     *
     * @return array{0: string|null, 1: string}
     */
    private function locate(Model $record): array
    {
        return match (true) {
            $record instanceof Incident => [(string) $record->getKey(), 'incident'],
            $record instanceof Assignment => [$record->incident_id, 'assignment'],
            $record instanceof AssignmentResponder => [$record->incident_id, 'assignment'],
            default => [null, 'system'],
        };
    }
}
