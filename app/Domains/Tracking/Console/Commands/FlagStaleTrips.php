<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Console\Commands;

use App\Domains\Tracking\Services\StalenessSweeper;
use Illuminate\Console\Command;

/**
 * Flags active trips whose device has gone dark (no recent fix) and escalates via
 * TripLostContact. Scheduled every minute; the sweep is idempotent and row-atomic,
 * so it is safe to run concurrently.
 */
final class FlagStaleTrips extends Command
{
    protected $signature = 'tracking:flag-stale';

    protected $description = 'Flag active trips that have gone dark and escalate.';

    public function handle(StalenessSweeper $sweeper): int
    {
        $flagged = $sweeper->sweep();

        $this->info("Flagged {$flagged} trip(s) as lost contact.");

        return self::SUCCESS;
    }
}
