<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Services;

use App\Domains\Tracking\DTOs\LocationFix;
use App\Domains\Tracking\Events\TripLocationUpdated;
use App\Domains\Tracking\Models\TripLocation;
use App\Domains\Trip\Events\TripReconnected;
use App\Domains\Trip\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ingests a batch of location fixes for a trip.
 *
 * Idempotent: duplicate fixes (same client id + recorded time, as a flaky network
 * will inevitably resend) are dropped by the unique constraint via insertOrIgnore.
 * The trip's durable last-known position is advanced only when the batch's newest
 * fix is actually newer — so a late-arriving buffered batch can't rewind it. The
 * live broadcast to dispatchers is coalesced via a Redis lock so a burst of fixes
 * can't overwhelm the websocket layer.
 */
final readonly class LocationIngestService
{
    /**
     * @param  list<LocationFix>  $fixes
     * @return int  number of fixes actually stored (excludes duplicates)
     */
    public function ingest(Trip $trip, array $fixes): int
    {
        $receivedAt = now()->toDateTimeString();

        $rows = array_map(static fn (LocationFix $fix): array => [
            'id' => (string) Str::orderedUuid(),
            'trip_id' => $trip->getKey(),
            'organization_id' => $trip->organization_id,
            'user_id' => $trip->user_id,
            'client_fix_id' => $fix->clientFixId,
            'lat' => $fix->lat,
            'lng' => $fix->lng,
            'accuracy' => $fix->accuracy,
            'speed' => $fix->speed,
            'heading' => $fix->heading,
            'recorded_at' => $fix->recordedAt->toDateTimeString(),
            'received_at' => $receivedAt,
            'created_at' => $receivedAt,
        ], $fixes);

        $stored = TripLocation::insertOrIgnore($rows);

        $newest = collect($fixes)
            ->sortByDesc(static fn (LocationFix $fix): int => $fix->recordedAt->getTimestamp())
            ->first();

        if ($newest !== null) {
            $this->advanceLastKnown($trip, $newest);
            $this->clearLostContactIfReconnected($trip);
            $this->broadcastIfDue($trip, $newest);
        }

        return $stored;
    }

    /**
     * A fix arrived: if the trip was flagged dark, clear the flag (re-arming the
     * sweep) and announce the reconnection. The atomic `whereNotNull` update means
     * only the transition fires the event, exactly once.
     */
    private function clearLostContactIfReconnected(Trip $trip): void
    {
        $cleared = DB::table('trips')
            ->where('id', $trip->getKey())
            ->whereNotNull('lost_contact_at')
            ->update(['lost_contact_at' => null]);

        if ($cleared > 0) {
            event(new TripReconnected($trip, null, ['status' => $trip->status->value]));
        }
    }

    /**
     * Conditional, atomic advance — never regress to an older buffered fix.
     */
    private function advanceLastKnown(Trip $trip, LocationFix $newest): void
    {
        $recordedAt = $newest->recordedAt->toDateTimeString();

        DB::table('trips')
            ->where('id', $trip->getKey())
            ->where(function ($query) use ($recordedAt): void {
                $query->whereNull('last_location_at')->orWhere('last_location_at', '<', $recordedAt);
            })
            ->update([
                'last_location_at' => $recordedAt,
                'last_lat' => $newest->lat,
                'last_lng' => $newest->lng,
            ]);
    }

    /**
     * Broadcast at most once per trip per coalesce window. The Redis `add`
     * (atomic SET NX EX) is the rate gate: it succeeds only when no broadcast has
     * fired for this trip within the window.
     */
    private function broadcastIfDue(Trip $trip, LocationFix $newest): void
    {
        $seconds = (int) config('sentrix.tracking.broadcast_coalesce_seconds', 2);

        if ($seconds > 0 && ! Cache::add("trip-location-broadcast:{$trip->getKey()}", true, $seconds)) {
            return;
        }

        event(new TripLocationUpdated(
            organization: (string) $trip->organization_id,
            tripId: (string) $trip->getKey(),
            lat: $newest->lat,
            lng: $newest->lng,
            recordedAt: $newest->recordedAt->toIso8601String(),
            speed: $newest->speed,
            heading: $newest->heading,
        ));
    }
}
