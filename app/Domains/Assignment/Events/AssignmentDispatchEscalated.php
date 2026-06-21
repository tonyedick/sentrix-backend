<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * Dispatch could not be filled in time (no acceptance before the deadline, or the
 * candidate pool was exhausted) and the assignment was escalated for human
 * intervention. Named "dispatch escalated" to keep it distinct from incident
 * severity escalation (event-map §10.2). Context carries the reason + level.
 */
final class AssignmentDispatchEscalated extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.dispatch_escalated';
    }
}
