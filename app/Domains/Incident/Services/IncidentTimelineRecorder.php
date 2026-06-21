<?php

declare(strict_types=1);

namespace App\Domains\Incident\Services;

use App\Domains\Incident\Events\TimelineEntryRecorded;
use App\Domains\Incident\Models\IncidentTimelineEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Persists durable, append-only entries to an incident's timeline
 * (incident_timeline_entries) and publishes a single lightweight domain event
 * ({@see TimelineEntryRecorded}) as a subscription point. It does NOT broadcast,
 * audit, or notify itself, so it remains safe to call from within another
 * domain's transaction (e.g. a projector reacting to incident/assignment events).
 *
 * Read-side timeline composition lives in {@see IncidentTimelineService}; this
 * service only writes.
 */
final readonly class IncidentTimelineRecorder
{
    /**
     * Append one entry to an incident's timeline.
     *
     * @param  string  $source  producing domain: incident|assignment|notification|ai|system
     * @param  Model|null  $subject  the related record (assignment, responder line, …); stored
     *                               as a decoupled polymorphic reference, no foreign key
     * @param  array<string, mixed>  $payload  entry detail / future-AI context
     */
    public function record(
        string $organizationId,
        string $incidentId,
        string $type,
        string $source,
        ?string $actorId = null,
        ?Model $subject = null,
        array $payload = [],
        ?CarbonInterface $occurredAt = null,
    ): IncidentTimelineEntry {
        $entry = DB::transaction(fn (): IncidentTimelineEntry => IncidentTimelineEntry::create([
            'organization_id' => $organizationId,
            'incident_id' => $incidentId,
            'type' => $type,
            'source' => $source,
            'actor_id' => $actorId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => $occurredAt ?? now(),
        ]));

        // Published after the entry is persisted, as a subscription point for
        // future (separately-approved) realtime / notification / AI listeners.
        event(new TimelineEntryRecorded($entry));

        return $entry;
    }
}
