<?php

declare(strict_types=1);

namespace App\Domains\Responder\Console\Commands;

use App\Domains\Responder\Services\DutyService;
use Illuminate\Console\Command;

/**
 * Activates duty shifts whose window has opened and closes those whose window
 * has ended, flipping responders on/off duty accordingly. Scheduled every
 * minute; idempotent and row-atomic, so safe to run concurrently.
 */
final class ProcessDutyShifts extends Command
{
    protected $signature = 'responders:process-duty';

    protected $description = 'Activate and close responder duty shifts at their boundaries.';

    public function handle(DutyService $duty): int
    {
        ['activated' => $activated, 'closed' => $closed] = $duty->processDueShifts();

        $this->info("Duty shifts processed: {$activated} activated, {$closed} closed.");

        return self::SUCCESS;
    }
}
