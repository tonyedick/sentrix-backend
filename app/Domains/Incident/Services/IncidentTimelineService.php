<?php

declare(strict_types=1);

namespace App\Domains\Incident\Services;

use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Models\IncidentTimelineEntry;

/**
 * Reads an incident's timeline. The sole source of truth is the
 * incident_timeline_entries table (populated by RecordTimelineEntryFromDomainEvent).
 *
 * No derivation, and no cross-domain reads into the Assignment domain's
 * assignment_events — the entries are already projected and stored. The legacy
 * derivation lives in {@see \App\Domains\Incident\Support\IncidentTimelineDeriver}
 * for one-time backfill only.
 *
 * Ordering: chronological by occurred_at, with id (ordered UUID) as a stable
 * tiebreaker. The (incident_id, occurred_at) index backs both ordering and
 * future pagination.
 */
final readonly class IncidentTimelineService
{
    /**
     * @return list<array{at: string|null, type: string, source: string, payload: array<string, mixed>}>
     */
    public function forIncident(Incident $incident): array
    {
        return IncidentTimelineEntry::query()
            ->where('incident_id', $incident->getKey())
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (IncidentTimelineEntry $entry): array => [
                'at' => $entry->occurred_at?->toIso8601String(),
                'type' => $entry->type,
                'source' => $entry->source,
                'payload' => $entry->payload ?? [],
            ])
            ->all();
    }
}
