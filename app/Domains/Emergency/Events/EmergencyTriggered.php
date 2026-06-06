<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An emergency was raised. The highest-priority realtime signal in the platform —
 * drives live emergency dashboards and (via listeners) responder notifications.
 */
final class EmergencyTriggered extends OrganizationRecordEvent
{
    /** Life-safety signal — jump the routine broadcast backlog. */
    public ?string $broadcastQueue = 'critical';

    public function action(): string
    {
        return 'emergency.triggered';
    }
}
