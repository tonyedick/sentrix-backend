<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An advisory dispatch recommendation (ranked candidate shortlist) was produced
 * for an incident/emergency. ADVISORY ONLY — nothing is auto-assigned.
 */
final class ResponderAssignmentRecommended extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.recommended';
    }
}
