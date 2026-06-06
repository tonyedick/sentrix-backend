<?php

declare(strict_types=1);

namespace App\Domains\Trip\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A monitored journey ended safely.
 */
final class TripCompleted extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'trip.completed';
    }
}
