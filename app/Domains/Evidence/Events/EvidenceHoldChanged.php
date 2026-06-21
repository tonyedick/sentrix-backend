<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A legal hold was placed on or released from an observation. Held evidence is
 * exempt from purge until the hold is released (enforced by the Retention domain).
 */
final class EvidenceHoldChanged extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'evidence.hold_changed';
    }
}
