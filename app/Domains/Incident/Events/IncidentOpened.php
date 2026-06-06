<?php

declare(strict_types=1);

namespace App\Domains\Incident\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A structured incident record was opened.
 */
final class IncidentOpened extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'incident.opened';
    }
}
