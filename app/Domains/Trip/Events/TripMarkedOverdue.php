<?php

declare(strict_types=1);

namespace App\Domains\Trip\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An active journey passed its expected arrival without completing — a likely
 * escalation trigger (a listener may auto-raise an emergency).
 */
final class TripMarkedOverdue extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'trip.overdue';
    }
}
