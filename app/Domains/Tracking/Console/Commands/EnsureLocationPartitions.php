<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Console\Commands;

use App\Domains\Tracking\Support\TripLocationPartitions;
use Illuminate\Console\Command;

/**
 * Rolls the trip_locations partition window forward. Scheduled monthly so the
 * next month's partition always exists before fixes land in it. (The DEFAULT
 * partition is a safety net; this keeps the hot ranges properly partitioned.)
 */
final class EnsureLocationPartitions extends Command
{
    protected $signature = 'tracking:ensure-partitions {--months=2 : How many months ahead to provision}';

    protected $description = 'Ensure upcoming monthly partitions for trip_locations exist.';

    public function handle(): int
    {
        TripLocationPartitions::ensureUpcoming((int) $this->option('months'));

        $this->info('Location partitions ensured.');

        return self::SUCCESS;
    }
}
