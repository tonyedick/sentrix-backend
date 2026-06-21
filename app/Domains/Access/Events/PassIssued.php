<?php

declare(strict_types=1);

namespace App\Domains\Access\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A visitor pass was minted. Broadcast to the organization channel and recorded
 * on the immutable audit trail (both handled by the base class).
 */
final class PassIssued extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'pass.issued';
    }
}
