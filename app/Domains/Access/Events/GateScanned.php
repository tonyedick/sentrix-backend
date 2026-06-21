<?php

declare(strict_types=1);

namespace App\Domains\Access\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A code was scanned at a gate (granted or denied) — the record is the appended
 * GateEvent. Broadcast to the organization channel and audited via the base.
 */
final class GateScanned extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'gate.scanned';
    }
}
