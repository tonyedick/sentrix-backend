<?php

declare(strict_types=1);

namespace App\Domains\Retention\Services;

use App\Domains\Evidence\Models\Observation;
use App\Domains\Evidence\Support\Enums\RetentionTier;
use App\Domains\Organization\Models\Organization;
use App\Domains\Retention\DTOs\StorageUsage;
use App\Domains\Retention\Events\EvidenceArchived;
use App\Domains\Retention\Events\RetentionSwept;
use App\Domains\Retention\Models\RetentionExport;
use App\Domains\Retention\Support\Enums\ExportFormat;
use App\Domains\Retention\Support\RetentionPolicy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Evidence vault storage lifecycle. Operates on Evidence's `observations`
 * (no primary table of its own) plus an append-only `retention_exports` log.
 *
 * Invariants enforced everywhere:
 *   - legal HOLD rows are NEVER re-tiered, archived, or purged;
 *   - SEALED rows are NEVER re-tiered (they have already been archived);
 *   - the sweep is idempotent and row-atomic (one UPDATE per tier by age window).
 */
final readonly class RetentionService
{
    /**
     * Re-tier this organization's non-hold, non-sealed observations by the age of
     * `observed_at` against the plan's cumulative windows:
     *
     *   <= hotCeiling                -> hot
     *   <= warmCeiling               -> warm
     *   <= coldCeiling (or older)    -> cold  (older rows stay cold, archive-eligible)
     *
     * Implemented as explicit WHERE-by-age UPDATEs (no row loading), so it is
     * atomic and idempotent. Returns the count MOVED into each tier.
     *
     * @return array{hot: int, warm: int, cold: int}
     */
    public function sweep(Organization $organization, ?RetentionPolicy $policy = null): array
    {
        $policy ??= RetentionPolicy::forOrganization($organization);

        return DB::transaction(function () use ($organization, $policy): array {
            $now = Carbon::now();

            // Window boundaries as absolute timestamps (age-of-observed_at against
            // cumulative day windows). Comparing a timestamp column to a computed
            // cutoff avoids any per-row Carbon diff (Carbon 3 diff* returns float).
            $hotFloor = $now->copy()->subDays($policy->hotCeiling());   // observed after this => hot
            $warmFloor = $now->copy()->subDays($policy->warmCeiling()); // observed after this => warm

            $base = static fn () => Observation::query()
                ->where('organization_id', $organization->getKey())
                ->where('hold', false)
                ->where('sealed', false);

            // hot: youngest window (observed_at strictly newer than the hot floor).
            $hot = (clone $base())
                ->where('observed_at', '>', $hotFloor)
                ->where('retention_tier', '!=', RetentionTier::Hot->value)
                ->update(['retention_tier' => RetentionTier::Hot->value]);

            // warm: between the hot floor and the warm floor. When warm_days is 0
            // this window is empty and the UPDATE matches nothing.
            $warm = (clone $base())
                ->where('observed_at', '<=', $hotFloor)
                ->where('observed_at', '>', $warmFloor)
                ->where('retention_tier', '!=', RetentionTier::Warm->value)
                ->update(['retention_tier' => RetentionTier::Warm->value]);

            // cold: everything older than the warm floor (the cold window + the
            // archive-eligible overflow both stay cold). Never re-touch archived
            // rows — those have already been sealed and exported.
            $cold = (clone $base())
                ->where('observed_at', '<=', $warmFloor)
                ->whereNotIn('retention_tier', [RetentionTier::Cold->value, RetentionTier::Archived->value])
                ->update(['retention_tier' => RetentionTier::Cold->value]);

            $moved = ['hot' => (int) $hot, 'warm' => (int) $warm, 'cold' => (int) $cold];

            event(new RetentionSwept((string) $organization->getKey(), $policy->plan, $moved));

            return $moved;
        });
    }

    /**
     * Archive-first export. Bundles the archive-eligible set — cold, non-hold,
     * non-sealed observations — OR an explicit id list (intersected with the same
     * guards) into a manifest, records a RetentionExport row, and seals + marks
     * those observations `archived`. Sealing is what licenses later deletion.
     *
     * @param  list<string>|null  $observationIds
     */
    public function archive(Organization $organization, ?array $observationIds, ?User $actor = null): RetentionExport
    {
        return DB::transaction(function () use ($organization, $observationIds, $actor): RetentionExport {
            $query = Observation::query()
                ->where('organization_id', $organization->getKey())
                ->where('hold', false)
                ->where('sealed', false)
                ->lockForUpdate();

            if ($observationIds !== null) {
                // Caller-supplied set: only valid uuids can match a uuid column on
                // Postgres; a non-uuid would raise a cast error, so filter them out.
                $valid = array_values(array_filter(
                    $observationIds,
                    static fn (mixed $id): bool => is_string($id) && Str::isUuid($id),
                ));

                if ($valid === []) {
                    $query->whereRaw('1 = 0'); // nothing can match
                } else {
                    $query->whereIn('id', $valid);
                }
            } else {
                // Default archive-eligible set: aged-out cold observations.
                $query->where('retention_tier', RetentionTier::Cold->value);
            }

            /** @var \Illuminate\Support\Collection<int, Observation> $observations */
            $observations = $query->orderBy('observed_at')->get();

            $manifest = $observations->map(static fn (Observation $o): array => [
                'id' => (string) $o->getKey(),
                'kind' => $o->kind->value,
                'plate' => $o->plate,
                'observed_at' => $o->observed_at?->toIso8601String(),
                'snapshot_url' => $o->snapshot_url,
                'clip_url' => $o->clip_url,
            ])->all();

            $export = RetentionExport::create([
                'organization_id' => $organization->getKey(),
                'exported_by' => $actor?->getKey(),
                'format' => ExportFormat::Json,
                'count' => $observations->count(),
                'manifest' => $manifest,
            ]);

            if ($observations->isNotEmpty()) {
                Observation::query()
                    ->whereIn('id', $observations->modelKeys())
                    ->update([
                        'retention_tier' => RetentionTier::Archived->value,
                        'sealed' => true,
                    ]);
            }

            event(new EvidenceArchived($export, $actor?->getKey(), [
                'count' => $observations->count(),
            ]));

            return $export;
        });
    }

    /**
     * Archived-first deletion: permanently delete observations that have been
     * archived (sealed into an export) AND are not on legal hold. Legal holds are
     * never purged. Idempotent.
     *
     * @return int  number of observations purged
     */
    public function purge(Organization $organization): int
    {
        return DB::transaction(function () use ($organization): int {
            return (int) Observation::query()
                ->where('organization_id', $organization->getKey())
                ->where('retention_tier', RetentionTier::Archived->value)
                ->where('hold', false)
                ->delete();
        });
    }

    /**
     * Read-only storage usage rollup for an organization vs its plan quota.
     */
    public function usage(Organization $organization, ?RetentionPolicy $policy = null): StorageUsage
    {
        $policy ??= RetentionPolicy::forOrganization($organization);

        $base = static fn () => Observation::query()
            ->where('organization_id', $organization->getKey());

        /** @var array<string, int> $byTier */
        $byTier = (clone $base())
            ->selectRaw('retention_tier, count(*) as aggregate')
            ->groupBy('retention_tier')
            ->pluck('aggregate', 'retention_tier')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $counts = [
            RetentionTier::Hot->value => $byTier[RetentionTier::Hot->value] ?? 0,
            RetentionTier::Warm->value => $byTier[RetentionTier::Warm->value] ?? 0,
            RetentionTier::Cold->value => $byTier[RetentionTier::Cold->value] ?? 0,
            RetentionTier::Archived->value => $byTier[RetentionTier::Archived->value] ?? 0,
        ];

        $total = (int) (clone $base())->count();
        $onHold = (int) (clone $base())->where('hold', true)->count();
        $archived = $counts[RetentionTier::Archived->value];

        // Estimated bytes: sum of the optional integer `bytes` key inside the
        // observations.attributes jsonb bag (else 0). The `->>` extraction yields
        // NULL when the key (or the whole bag) is absent, which COALESCEs to 0; we
        // only cast values that look like a non-negative integer so a stray
        // non-numeric `bytes` can never raise a Postgres cast error. (We avoid the
        // jsonb `?` existence operator here — it collides with PDO's `?`
        // placeholder in a raw expression.)
        $estimatedBytes = (int) (clone $base())
            ->sum(DB::raw(
                "CASE WHEN attributes->>'bytes' ~ '^[0-9]+$' THEN (attributes->>'bytes')::bigint ELSE 0 END"
            ));

        $quotaGb = $policy->quotaGb;
        $quotaBytes = $quotaGb * 1024 * 1024 * 1024;
        $pctOfQuota = $quotaBytes > 0
            ? round(($estimatedBytes / $quotaBytes) * 100, 4)
            : 0.0;

        return new StorageUsage(
            plan: $policy->plan,
            quotaGb: $quotaGb,
            countsByTier: $counts,
            total: $total,
            onLegalHold: $onHold,
            archived: $archived,
            estimatedBytes: $estimatedBytes,
            pctOfQuota: $pctOfQuota,
        );
    }
}
