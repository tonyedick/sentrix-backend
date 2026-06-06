<?php

declare(strict_types=1);

namespace App\Domains\Incident\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An incident was escalated to a higher tier — a key signal for paging
 * on-call/senior responders via listeners.
 */
final class IncidentEscalated extends OrganizationRecordEvent
{
    /** Escalations page senior/on-call responders — prioritise delivery. */
    public ?string $broadcastQueue = 'critical';

    public function action(): string
    {
        return 'incident.escalated';
    }
}
