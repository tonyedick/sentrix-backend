<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An emergency was stood down as a false alarm before resolution.
 */
final class EmergencyCancelled extends OrganizationRecordEvent
{
    public ?string $broadcastQueue = 'critical';

    public function action(): string
    {
        return 'emergency.cancelled';
    }
}
