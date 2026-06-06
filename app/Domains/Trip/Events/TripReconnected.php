<?php

declare(strict_types=1);

namespace App\Domains\Trip\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A previously-dark trip started reporting locations again.
 */
final class TripReconnected extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'trip.reconnected';
    }
}
