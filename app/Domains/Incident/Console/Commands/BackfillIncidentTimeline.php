<?php

declare(strict_types=1);

namespace App\Domains\Incident\Console\Commands;

use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Models\IncidentTimelineEntry;
use App\Domains\Incident\Support\IncidentTimelineDeriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill of incident_timeline_entries for incidents that predate the
 * timeline projector. Reconstructs each incident's timeline from the legacy
 * sources (milestones + assignment_events) via IncidentTimelineDeriver and
 * inserts the rows directly (no events fired).
 *
 * Idempotent: an incident that already has any timeline entries is skipped, so
 * the command is safe to run repeatedly and either side of the read-path flip.
 */
final class BackfillIncidentTimeline extends Command
{
    protected $signature = 'incidents:backfill-timeline';

    protected $description = 'Backfill incident_timeline_entries from legacy sources for incidents that have none.';

    public function handle(IncidentTimelineDeriver $deriver): int
    {
        $backfilled = 0;
        $entries = 0;

        Incident::query()
            ->withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($incidents) use ($deriver, &$backfilled, &$entries): void {
                foreach ($incidents as $incident) {
                    $alreadyHas = IncidentTimelineEntry::query()
                        ->where('incident_id', $incident->getKey())
                        ->exists();

                    if ($alreadyHas) {
                        continue;
                    }

                    $rows = $deriver->derive($incident);

                    if ($rows === []) {
                        continue;
                    }

                    DB::transaction(function () use ($incident, $rows, &$entries): void {
                        foreach ($rows as $row) {
                            IncidentTimelineEntry::create([
                                'organization_id' => $incident->organization_id,
                                'incident_id' => $incident->getKey(),
                                'type' => $row['type'],
                                'source' => $row['source'],
                                'actor_id' => $row['actor_id'],
                                'payload' => $row['payload'] === [] ? null : $row['payload'],
                                'occurred_at' => $row['occurred_at'],
                            ]);
                            $entries++;
                        }
                    });

                    $backfilled++;
                }
            });

        $this->info("Backfilled {$backfilled} incident(s), {$entries} timeline entr(ies).");

        return self::SUCCESS;
    }
}
