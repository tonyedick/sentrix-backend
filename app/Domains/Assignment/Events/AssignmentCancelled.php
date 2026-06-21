<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An assignment was cancelled; all active responders were stood down.
 */
final class AssignmentCancelled extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.cancelled';
    }
}
