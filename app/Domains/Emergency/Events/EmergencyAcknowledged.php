<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder/dispatcher took ownership of an emergency.
 */
final class EmergencyAcknowledged extends OrganizationRecordEvent
{
    public ?string $broadcastQueue = 'critical';

    public function action(): string
    {
        return 'emergency.acknowledged';
    }
}
