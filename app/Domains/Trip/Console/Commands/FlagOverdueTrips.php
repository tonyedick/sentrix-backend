<?php

declare(strict_types=1);

namespace App\Domains\Trip\Console\Commands;

use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Services\TripService;
use Illuminate\Console\Command;

/**
 * Sweeps active trips whose expected arrival has elapsed and flags them overdue.
 * This is the entry point of the escalation chain: each flagged trip emits
 * TripMarkedOverdue, which (by default) auto-raises an emergency.
 *
 * Scheduled every minute (see routes/console.php). Safe to run concurrently —
 * markOverdue locks each row and is idempotent.
 */
final class FlagOverdueTrips extends Command
{
    protected $signature = 'trips:flag-overdue';

    protected $description = 'Flag active trips past their expected arrival as overdue.';

    public function handle(TripService $trips): int
    {
        $flagged = 0;

        Trip::overdueCandidates()->chunkById(100, function ($candidates) use ($trips, &$flagged): void {
            foreach ($candidates as $trip) {
                $trips->markOverdue($trip);
                $flagged++;
            }
        });

        $this->info("Flagged {$flagged} overdue trip(s).");

        return self::SUCCESS;
    }
}
