<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A physical hardware device was registered into an organization's registry.
 */
final class DeviceRegistered extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'device.registered';
    }
}
