<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An emergency was handled and closed out.
 */
final class EmergencyResolved extends OrganizationRecordEvent
{
    public ?string $broadcastQueue = 'critical';

    public function action(): string
    {
        return 'emergency.resolved';
    }
}
