<?php

declare(strict_types=1);

namespace App\Domains\Incident\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An incident was resolved (pending closure).
 */
final class IncidentResolved extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'incident.resolved';
    }
}
