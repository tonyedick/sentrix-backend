<?php

declare(strict_types=1);

namespace App\Domains\Responder\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder's operational status changed (e.g. available → engaged, or
 * off_duty → available). The context carries `from` and `to`.
 */
final class ResponderStatusChanged extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'responder.status_changed';
    }
}
