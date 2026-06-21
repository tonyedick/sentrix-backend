<?php

declare(strict_types=1);

namespace App\Domains\Responder\Services;

use App\Domains\Responder\DTOs\ResponderLocationFix;
use App\Domains\Responder\Events\ResponderLocationUpdated;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Models\ResponderLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ingests a batch of responder location fixes. Mirrors the trip-tracking
 * pipeline: idempotent insertOrIgnore, a conditional (never-rewind) advance of
 * the denormalised last-known position, and a Redis-coalesced broadcast so a
 * burst of fixes can't flood the dispatcher map.
 */
final readonly class ResponderLocationService
{
    /**
     * @param  list<ResponderLocationFix>  $fixes
     * @return int  number of fixes actually stored (excludes duplicates)
     */
    public function ingest(Responder $responder, array $fixes): int
    {
        if ($fixes === []) {
            return 0;
        }

        $receivedAt = now()->toDateTimeString();

        $rows = array_map(static fn (ResponderLocationFix $fix): array => [
            'id' => (string) Str::orderedUuid(),
            'responder_id' => $responder->getKey(),
            'organization_id' => $responder->organization_id,
            'user_id' => $responder->user_id,
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

        $stored = ResponderLocation::insertOrIgnore($rows);

        $newest = collect($fixes)
            ->sortByDesc(static fn (ResponderLocationFix $fix): int => $fix->recordedAt->getTimestamp())
            ->first();

        if ($newest !== null) {
            $this->advanceLastKnown($responder, $newest);
            $this->broadcastIfDue($responder, $newest);
        }

        return $stored;
    }

    /**
     * Conditional, atomic advance — never regress to an older buffered fix.
     */
    private function advanceLastKnown(Responder $responder, ResponderLocationFix $newest): void
    {
        $recordedAt = $newest->recordedAt->toDateTimeString();

        DB::table('responders')
            ->where('id', $responder->getKey())
            ->where(function ($query) use ($recordedAt): void {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $recordedAt);
            })
            ->update([
                'last_seen_at' => $recordedAt,
                'last_lat' => $newest->lat,
                'last_lng' => $newest->lng,
            ]);
    }

    /**
     * Broadcast at most once per responder per coalesce window. The Redis `add`
     * (atomic SET NX EX) is the rate gate.
     */
    private function broadcastIfDue(Responder $responder, ResponderLocationFix $newest): void
    {
        $seconds = (int) config('sentrix.responders.location_broadcast_coalesce_seconds', 5);

        if ($seconds > 0 && ! Cache::add("responder-location-broadcast:{$responder->getKey()}", true, $seconds)) {
            return;
        }

        event(new ResponderLocationUpdated(
            organization: (string) $responder->organization_id,
            responderId: (string) $responder->getKey(),
            lat: $newest->lat,
            lng: $newest->lng,
            recordedAt: $newest->recordedAt->toIso8601String(),
            speed: $newest->speed,
            heading: $newest->heading,
        ));
    }
}
