<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An assignment (coordination record) was opened for an incident.
 */
final class AssignmentCreated extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.created';
    }
}
