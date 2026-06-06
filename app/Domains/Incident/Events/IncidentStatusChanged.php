<?php

declare(strict_types=1);

namespace App\Domains\Incident\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A non-milestone state transition (e.g. open → investigating, or reopening a
 * resolved incident). Milestone transitions have dedicated events
 * ({@see IncidentEscalated}, {@see IncidentResolved}, {@see IncidentClosed}).
 * The context carries `from` and `to`.
 */
final class IncidentStatusChanged extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'incident.status_changed';
    }
}
