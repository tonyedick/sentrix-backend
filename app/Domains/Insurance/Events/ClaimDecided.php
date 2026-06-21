<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A filed claim was decided (approved or rejected). The outcome is carried in
 * the event context.
 */
final class ClaimDecided extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'claim.decided';
    }
}
