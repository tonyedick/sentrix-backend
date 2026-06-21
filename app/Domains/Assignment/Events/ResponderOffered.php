<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder was offered a role on an assignment (dispatch). The record is the
 * AssignmentResponder line; context carries the role + target.
 */
final class ResponderOffered extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_offered';
    }
}
