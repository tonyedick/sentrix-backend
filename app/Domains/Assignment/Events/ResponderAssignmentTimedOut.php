<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder's offer expired without acceptance. A re-offer or escalation
 * should follow (escalation slice).
 */
final class ResponderAssignmentTimedOut extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_timed_out';
    }
}
