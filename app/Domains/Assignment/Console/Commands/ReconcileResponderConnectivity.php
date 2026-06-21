<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Console\Commands;

use App\Domains\Assignment\Jobs\ReassignAssignmentJob;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Services\DispatchService;
use App\Domains\Assignment\Support\Enums\AssignmentResponderStatus;
use Illuminate\Console\Command;

/**
 * Stands down committed responders who have gone dark (no location fix within the
 * connectivity window) and queues a reassignment for their role. Closes
 * event-map §8.3. Scheduled every minute; idempotent — a line is only acted on
 * while it is still committed.
 *
 * Responders with no location history (last_seen_at null) are left alone — never
 * reported is not the same as lost connectivity.
 */
final class ReconcileResponderConnectivity extends Command
{
    protected $signature = 'assignments:reconcile-connectivity';

    protected $description = 'Stand down committed responders that have lost connectivity and reassign.';

    public function handle(DispatchService $dispatch): int
    {
        $staleAfter = (int) config('sentrix.assignments.connectivity_stale_after_seconds', 180);
        $threshold = now()->subSeconds($staleAfter);
        $count = 0;

        AssignmentResponder::query()
            ->whereIn('status', [
                AssignmentResponderStatus::Accepted->value,
                AssignmentResponderStatus::EnRoute->value,
                AssignmentResponderStatus::OnScene->value,
            ])
            ->whereHas('responder', fn ($query) => $query
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '<', $threshold))
            ->orderBy('id')
            ->get()
            ->each(function (AssignmentResponder $line) use ($dispatch, &$count): void {
                $dispatch->standDownLine($line, null, 'connectivity_lost');
                ReassignAssignmentJob::dispatch($line->assignment_id, $line->role->value);
                $count++;
            });

        $this->info("Reconciled {$count} responder(s) that lost connectivity.");

        return self::SUCCESS;
    }
}
