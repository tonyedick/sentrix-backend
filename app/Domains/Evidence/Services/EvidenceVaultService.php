<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Services;

use App\Domains\Evidence\DTOs\ObservationData;
use App\Domains\Evidence\Events\EvidenceHoldChanged;
use App\Domains\Evidence\Events\ObservationsRecorded;
use App\Domains\Evidence\Models\Observation;
use App\Domains\Evidence\Support\Enums\RetentionTier;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns the evidence vault writes. Batch ingest is the hot path (continuous
 * metadata capture), so it inserts inside one transaction and emits a SINGLE
 * batch event rather than one per row. Hold/bookmark toggles lock and re-read.
 */
final readonly class EvidenceVaultService
{
    /**
     * Ingest a batch of observations. Returns the created records (in input
     * order) so the controller can echo back the count and ids.
     *
     * @param  list<ObservationData>  $items
     * @return list<Observation>
     */
    public function record(Organization $organization, array $items, ?User $actor = null): array
    {
        return DB::transaction(function () use ($organization, $items, $actor): array {
            $recorded = [];

            foreach ($items as $item) {
                $observation = Observation::create([
                    'organization_id' => $organization->getKey(),
                    'camera_source_id' => $item->cameraSourceId,
                    'kind' => $item->kind,
                    'label' => $item->label,
                    'attributes' => $item->attributes,
                    'plate' => $item->plate,
                    'confidence' => $item->confidence,
                    'severity' => $item->severity,
                    'snapshot_url' => $item->snapshotUrl,
                    'clip_url' => $item->clipUrl,
                    'lat' => $item->lat,
                    'lng' => $item->lng,
                    'observed_at' => $item->observedAt !== null
                        ? Carbon::parse($item->observedAt)
                        : now(),
                    'retention_tier' => RetentionTier::Hot,
                ]);

                $recorded[] = $observation;
            }

            // One event per batch keeps the ingest path light. We attach a
            // representative record (the first) so the org-scoped broadcast/audit
            // has an organization_id, and the batch count in context.
            if ($recorded !== []) {
                event(new ObservationsRecorded($recorded[0], $actor?->getKey(), [
                    'count' => count($recorded),
                ]));
            }

            return $recorded;
        });
    }

    /**
     * Toggle (or explicitly set) the legal hold on an observation. Locks and
     * re-reads so the flip is atomic; idempotent for an explicit no-op set.
     */
    public function toggleHold(Observation $observation, ?bool $hold, ?User $actor = null): Observation
    {
        return DB::transaction(function () use ($observation, $hold, $actor): Observation {
            /** @var Observation $locked */
            $locked = Observation::query()->whereKey($observation->getKey())->lockForUpdate()->firstOrFail();

            $target = $hold ?? ! $locked->hold;

            if ($locked->hold === $target) {
                return $locked; // idempotent
            }

            $locked->hold = $target;
            $locked->save();

            event(new EvidenceHoldChanged($locked, $actor?->getKey(), [
                'hold' => $locked->hold,
            ]));

            return $locked;
        });
    }

    /**
     * Toggle (or explicitly set) the bookmark on an observation.
     */
    public function toggleBookmark(Observation $observation, ?bool $bookmarked, ?User $actor = null): Observation
    {
        return DB::transaction(function () use ($observation, $bookmarked, $actor): Observation {
            /** @var Observation $locked */
            $locked = Observation::query()->whereKey($observation->getKey())->lockForUpdate()->firstOrFail();

            $target = $bookmarked ?? ! $locked->bookmarked;

            if ($locked->bookmarked === $target) {
                return $locked; // idempotent
            }

            $locked->bookmarked = $target;
            $locked->save();

            return $locked;
        });
    }
}
