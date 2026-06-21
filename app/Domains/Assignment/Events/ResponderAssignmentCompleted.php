<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder completed their assignment line (per-line completion via the
 * explicit complete action). Distinct from AssignmentCompleted, which closes the
 * whole aggregate.
 */
final class ResponderAssignmentCompleted extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_completed';
    }
}
