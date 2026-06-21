<?php

declare(strict_types=1);

namespace App\Domains\Responder\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder certification is approaching its expiry date (within the
 * configured warning window). Drives a reminder notification.
 */
final class ResponderCertificationExpiring extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'responder.certification_expiring';
    }
}
