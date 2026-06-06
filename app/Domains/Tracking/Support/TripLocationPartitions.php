<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Manages the monthly range partitions of `trip_locations`.
 *
 * A DEFAULT partition guarantees inserts never fail (e.g. a device with a badly
 * skewed clock), while monthly partitions keep the hot ranges small. The migration
 * seeds the current window; a scheduled command rolls the window forward.
 */
final class TripLocationPartitions
{
    /**
     * Ensure the partition covering $month exists. No-op on non-PostgreSQL
     * drivers (where the table is created non-partitioned).
     */
    public static function ensureForMonth(DateTimeInterface $month): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $from = CarbonImmutable::instance($month)->startOfMonth();
        $to = $from->addMonth();
        $name = 'trip_locations_y'.$from->format('Y').'m'.$from->format('m');

        DB::statement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s PARTITION OF trip_locations FOR VALUES FROM (%s) TO (%s)',
            '"'.$name.'"',
            "'".$from->toDateString()."'",
            "'".$to->toDateString()."'",
        ));
    }

    /**
     * Ensure partitions exist for this month through $monthsAhead months out.
     */
    public static function ensureUpcoming(int $monthsAhead = 2): void
    {
        $start = CarbonImmutable::now()->startOfMonth();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            self::ensureForMonth($start->addMonths($i));
        }
    }
}
