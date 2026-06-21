<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A hardware device checked back in: marked active with a fresh last_seen_at.
 */
final class DeviceResynced extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'device.resynced';
    }
}
