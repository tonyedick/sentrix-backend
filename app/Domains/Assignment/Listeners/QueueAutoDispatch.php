<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Listeners;

use App\Domains\Assignment\Events\AssignmentCreated;
use App\Domains\Assignment\Jobs\DispatchAssignmentJob;
use App\Domains\Assignment\Models\Assignment;

/**
 * When an assignment is opened in auto mode, queue the auto-dispatch job (if
 * auto-dispatch is enabled). Manual assignments are untouched.
 */
final class QueueAutoDispatch
{
    public function handle(AssignmentCreated $event): void
    {
        if (! config('sentrix.assignments.auto_dispatch', true)) {
            return;
        }

        $assignment = $event->record;

        if (! $assignment instanceof Assignment || $assignment->dispatch_mode !== 'auto') {
            return;
        }

        DispatchAssignmentJob::dispatch($assignment->getKey());
    }
}
