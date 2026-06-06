<?php

declare(strict_types=1);

namespace App\Domains\Trip\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A monitored journey began. Broadcast to the organization channel and audited.
 */
final class TripStarted extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'trip.started';
    }
}
