<?php

declare(strict_types=1);

namespace App\Domains\Incident\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An incident reached its terminal closed state.
 */
final class IncidentClosed extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'incident.closed';
    }
}
