<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder declined their assignment offer.
 */
final class ResponderDeclinedAssignment extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_declined';
    }
}
