<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Console\Commands;

use App\Domains\Ledger\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Dead-man sweep for the Ledger: flag active sources that have written before
 * but have gone silent past the stale window (default 15 min) and that have not
 * already been alerted. Each newly flagged source fires a SourceWentStale event.
 *
 * Idempotent and re-armed by the next write, so it is safe to run on a schedule.
 * (Schedule it in routes/console.php or the app scheduler, e.g. ->everyFiveMinutes().)
 */
final class FlagStaleSources extends Command
{
    protected $signature = 'ledger:flag-stale {--minutes= : Override the stale window in minutes}';

    protected $description = 'Flag active Ledger sources that have gone silent past the stale window.';

    public function handle(LedgerService $ledger): int
    {
        $minutes = $this->option('minutes');
        $window = is_numeric($minutes) ? (int) $minutes : null;

        $flagged = $ledger->sweepStale($window);

        $this->info("Flagged {$flagged} stale source(s).");

        return self::SUCCESS;
    }
}
