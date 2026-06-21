<?php

declare(strict_types=1);

namespace App\Domains\Access\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A visitor pass was revoked (terminal). Broadcast + audited via the base class.
 */
final class PassRevoked extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'pass.revoked';
    }
}
