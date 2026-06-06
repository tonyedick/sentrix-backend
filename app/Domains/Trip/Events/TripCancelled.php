<?php

declare(strict_types=1);

namespace App\Domains\Trip\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A monitored journey was called off before completion.
 */
final class TripCancelled extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'trip.cancelled';
    }
}
