<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Console\Commands;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Services\EscalationService;
use App\Domains\Assignment\Support\Enums\AssignmentStatus;
use Illuminate\Console\Command;

/**
 * Escalates assignments whose acceptance deadline has passed without being
 * filled. Scheduled every minute; idempotent — escalated assignments fall out of
 * the query, so an assignment is escalated once per deadline.
 */
final class EscalateOverdueAssignments extends Command
{
    protected $signature = 'assignments:escalate-overdue';

    protected $description = 'Escalate assignments past their acceptance deadline that are not yet filled.';

    public function handle(EscalationService $escalation): int
    {
        $count = 0;

        Assignment::query()
            ->whereNotNull('acceptance_deadline_at')
            ->where('acceptance_deadline_at', '<', now())
            ->whereIn('status', [
                AssignmentStatus::Pending->value,
                AssignmentStatus::Dispatching->value,
                AssignmentStatus::PartiallyFilled->value,
            ])
            ->orderBy('id')
            ->get()
            ->each(function (Assignment $assignment) use ($escalation, &$count): void {
                $escalation->escalate($assignment, 'acceptance_deadline');
                $count++;
            });

        $this->info("Escalated {$count} overdue assignment(s).");

        return self::SUCCESS;
    }
}
