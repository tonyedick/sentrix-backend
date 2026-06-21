<?php

declare(strict_types=1);

namespace App\Domains\Incident\Events;

use App\Domains\Incident\Models\IncidentTimelineEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new entry was appended to an incident's timeline.
 *
 * Deliberately a plain, framework-light domain event — it does NOT broadcast or
 * audit itself (unlike OrganizationRecordEvent), because the timeline entry is
 * already a durable record and is usually a projection of an event that was
 * itself audited; auditing/broadcasting again would duplicate.
 *
 * It exists purely as a subscription point: future queue, realtime-broadcast,
 * notification, or AI listeners attach via Event::listen with no change to the
 * recorder. SerializesModels lets those future listeners be queued safely.
 *
 * Dispatch timing: published by IncidentTimelineRecorder AFTER the entry is
 * persisted. Any future queued listener should broadcast/act after commit.
 */
final class TimelineEntryRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly IncidentTimelineEntry $entry) {}
}
