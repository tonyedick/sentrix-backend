<?php

declare(strict_types=1);

namespace App\Domains\Trip\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An active trip's device stopped reporting (went dark). Broadcast + audited, and
 * a likely escalation trigger (a listener raises a "lost contact" emergency).
 */
final class TripLostContact extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'trip.lost_contact';
    }
}
