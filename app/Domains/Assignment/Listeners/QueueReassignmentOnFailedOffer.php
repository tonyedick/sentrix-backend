<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Listeners;

use App\Domains\Assignment\Jobs\ReassignAssignmentJob;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * On a declined or timed-out offer, queue a reassignment for that role (if
 * auto-reassign is enabled). Light: just dispatches the job, which re-checks
 * whether the role is still needed. Handles both ResponderDeclinedAssignment and
 * ResponderAssignmentTimedOut (the record is the AssignmentResponder line).
 */
final class QueueReassignmentOnFailedOffer
{
    public function handle(OrganizationRecordEvent $event): void
    {
        if (! config('sentrix.assignments.auto_reassign', true)) {
            return;
        }

        $line = $event->record;

        if (! $line instanceof AssignmentResponder || $line->assignment_id === null) {
            return;
        }

        ReassignAssignmentJob::dispatch($line->assignment_id, $line->role->value);
    }
}
