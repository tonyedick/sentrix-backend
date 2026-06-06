<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Services;

use App\Domains\Trip\Events\TripLostContact;
use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Support\Enums\TripStatus;
use Illuminate\Support\Facades\DB;

/**
 * Detects active trips whose device has gone dark — last fix older than the
 * configured threshold — and flags each exactly once (`lost_contact_at`), firing
 * TripLostContact so the escalation chain can raise an emergency. The flag is
 * cleared when a fix arrives again (see LocationIngestService), so a trip can be
 * re-flagged if it goes dark a second time.
 */
final readonly class StalenessSweeper
{
    /**
     * @return int  number of trips newly flagged as lost-contact
     */
    public function sweep(): int
    {
        $cutoff = now()
            ->subSeconds((int) config('sentrix.tracking.stale_after_seconds', 300))
            ->toDateTimeString();

        $flagged = 0;

        Trip::query()
            ->whereIn('status', [TripStatus::Active->value, TripStatus::Overdue->value])
            ->whereNotNull('last_location_at')
            ->whereNull('lost_contact_at')
            ->where('last_location_at', '<', $cutoff)
            ->chunkById(100, function ($trips) use (&$flagged): void {
                foreach ($trips as $trip) {
                    // Atomic transition — only one sweep wins, so the event fires once.
                    $marked = DB::table('trips')
                        ->where('id', $trip->getKey())
                        ->whereNull('lost_contact_at')
                        ->update(['lost_contact_at' => now()]);

                    if ($marked > 0) {
                        event(new TripLostContact($trip, null, [
                            'status' => $trip->status->value,
                            'last_location_at' => $trip->last_location_at?->toIso8601String(),
                        ]));

                        $flagged++;
                    }
                }
            });

        return $flagged;
    }
}
