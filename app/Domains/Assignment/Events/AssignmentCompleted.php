<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An assignment was completed (its primary responder finished handling it).
 */
final class AssignmentCompleted extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.completed';
    }
}
