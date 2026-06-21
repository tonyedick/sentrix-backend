<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\DTOs\IngestWriteData;
use App\Domains\Ledger\DTOs\OnboardSourceData;
use App\Domains\Ledger\Events\SourceWentStale;
use App\Domains\Ledger\Events\SourceWriteRecorded;
use App\Domains\Ledger\Models\LedgerSource;
use App\Domains\Ledger\Models\LedgerWrite;
use App\Domains\Ledger\Support\Enums\SourceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the Ledger write-spine: source onboarding + lifecycle, raw-key issuance
 * (hashed at rest), write ingestion (which bumps counters + re-arms the dead-man
 * switch), the stale sweep, and the read-side stats rollup.
 *
 * Writes run in a transaction; contended source rows are locked and re-read
 * before mutation. Stateless — holds only readonly dependencies.
 */
final readonly class LedgerService
{
    /**
     * Minutes since last_write_at after which an active source that has written
     * before is considered stale (dead-man window). Mirrors the Omni reference.
     */
    public const STALE_AFTER_MINUTES = 15;

    /** Raw ingest key prefix (matches the ecosystem's LKEY_ convention). */
    private const KEY_PREFIX = 'LKEY_';

    /**
     * Onboard a new source (status pending). Returns the persisted source plus
     * the RAW ingest key, which is surfaced exactly once and never stored.
     *
     * @return array{source: LedgerSource, raw_key: string}
     */
    public function onboard(OnboardSourceData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $rawKey = $this->generateRawKey();

            $source = LedgerSource::create([
                'slug' => $data->slug ?? $this->slugify($data->name),
                'name' => $data->name,
                'product' => $data->product,
                'kind' => $data->kind,
                'organization_id' => $data->organizationId,
                'status' => SourceStatus::Pending,
                'key_hash' => $this->hashKey($rawKey),
                'write_count' => 0,
                'stale_alerted' => false,
                'metadata' => $data->metadata,
            ]);

            return ['source' => $source, 'raw_key' => $rawKey];
        });
    }

    public function activate(LedgerSource $source): LedgerSource
    {
        return $this->transition($source, SourceStatus::Active);
    }

    public function suspend(LedgerSource $source): LedgerSource
    {
        return $this->transition($source, SourceStatus::Suspended);
    }

    public function revoke(LedgerSource $source): LedgerSource
    {
        return $this->transition($source, SourceStatus::Revoked);
    }

    /**
     * Issue a fresh ingest key, invalidating the old one. Returns the raw key
     * once. Revoked sources are terminal and cannot rotate.
     *
     * @return array{source: LedgerSource, raw_key: string}
     */
    public function rotateKey(LedgerSource $source): array
    {
        return DB::transaction(function () use ($source): array {
            /** @var LedgerSource $locked */
            $locked = LedgerSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();

            abort_if($locked->status === SourceStatus::Revoked, 409, 'source_revoked');

            $rawKey = $this->generateRawKey();
            $locked->update(['key_hash' => $this->hashKey($rawKey)]);

            return ['source' => $locked, 'raw_key' => $rawKey];
        });
    }

    /**
     * Record a write reported by an (already authenticated) active source. Bumps
     * the source's write_count + last_write_at and re-arms the dead-man switch.
     * Concurrency-safe: the source row is locked and re-read before mutation.
     */
    public function ingest(LedgerSource $source, IngestWriteData $data): LedgerWrite
    {
        return DB::transaction(function () use ($source, $data): LedgerWrite {
            /** @var LedgerSource $locked */
            $locked = LedgerSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();

            $now = now();

            $write = LedgerWrite::create([
                'ledger_source_id' => $locked->getKey(),
                'type' => $data->type,
                'summary' => $data->summary,
                'ref' => $data->ref,
                'organization_id' => $data->organizationId,
                'recorded_at' => $now,
            ]);

            $locked->update([
                'write_count' => $locked->write_count + 1,
                'last_write_at' => $now,
                'stale_alerted' => false, // a fresh write re-arms the dead-man switch
            ]);

            event(new SourceWriteRecorded($locked, $write));

            return $write;
        });
    }

    /**
     * Dead-man sweep: flag active sources that have written before but have gone
     * silent past the stale window and have not already been alerted. Each newly
     * flagged source fires a SourceWentStale event. Returns the count flagged.
     */
    public function sweepStale(?int $afterMinutes = null): int
    {
        $window = $afterMinutes ?? self::STALE_AFTER_MINUTES;
        $threshold = now()->subMinutes($window);

        $flagged = 0;

        LedgerSource::query()
            ->where('status', SourceStatus::Active->value)
            ->where('stale_alerted', false)
            ->whereNotNull('last_write_at')
            ->where('last_write_at', '<', $threshold)
            ->orderBy('id')
            ->chunkById(200, function ($sources) use (&$flagged): void {
                foreach ($sources as $source) {
                    DB::transaction(function () use ($source, &$flagged): void {
                        /** @var LedgerSource $locked */
                        $locked = LedgerSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();

                        // Re-check under lock: a concurrent ingest may have re-armed it.
                        if ($locked->status !== SourceStatus::Active
                            || $locked->stale_alerted
                            || ! $locked->last_write_at instanceof Carbon
                            || $locked->last_write_at->gte(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
                            return;
                        }

                        $locked->update(['stale_alerted' => true]);

                        $silentForMinutes = (int) $locked->last_write_at->diffInMinutes(now());

                        event(new SourceWentStale($locked, $silentForMinutes));

                        $flagged++;
                    });
                }
            });

        return $flagged;
    }

    /**
     * Global Ledger stats: total write volume, active source count, and a
     * per-source health rollup.
     *
     * @return array{
     *     total_writes: int,
     *     active_sources: int,
     *     source_count: int,
     *     sources: list<array{id: string, slug: string, name: string, product: string|null, kind: string, status: string, write_count: int, last_write_at: string|null, health: string}>
     * }
     */
    public function stats(): array
    {
        $sources = LedgerSource::query()->orderBy('name')->get();

        $rows = $sources->map(fn (LedgerSource $source): array => [
            'id' => $source->id,
            'slug' => $source->slug,
            'name' => $source->name,
            'product' => $source->product,
            'kind' => $source->kind->value,
            'status' => $source->status->value,
            'write_count' => $source->write_count,
            'last_write_at' => $source->last_write_at?->toIso8601String(),
            'health' => $this->health($source),
        ])->all();

        return [
            'total_writes' => (int) $sources->sum('write_count'),
            'active_sources' => $sources->where('status', SourceStatus::Active)->count(),
            'source_count' => $sources->count(),
            'sources' => $rows,
        ];
    }

    /**
     * Derived health string for a source (mirrors the Omni reference semantics).
     */
    public function health(LedgerSource $source): string
    {
        return match ($source->status) {
            SourceStatus::Revoked => 'offline',
            SourceStatus::Suspended => 'suspended',
            SourceStatus::Pending => 'pending',
            SourceStatus::Active => $this->activeHealth($source),
        };
    }

    private function activeHealth(LedgerSource $source): string
    {
        $last = $source->last_write_at;

        if (! $last instanceof Carbon) {
            return 'idle';
        }

        return $last->gt(now()->subMinutes(self::STALE_AFTER_MINUTES))
            ? 'healthy'
            : 'stale';
    }

    /**
     * Apply a lifecycle transition. Revoked is terminal; pending cannot be
     * re-entered. Idempotent on the target status.
     */
    private function transition(LedgerSource $source, SourceStatus $target): LedgerSource
    {
        return DB::transaction(function () use ($source, $target): LedgerSource {
            /** @var LedgerSource $locked */
            $locked = LedgerSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === $target) {
                return $locked; // idempotent
            }

            abort_if($locked->status === SourceStatus::Revoked, 409, 'source_revoked');

            $locked->update(['status' => $target]);

            return $locked;
        });
    }

    private function generateRawKey(): string
    {
        return self::KEY_PREFIX.Str::random(40);
    }

    private function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    private function slugify(string $name): string
    {
        return Str::of($name)->slug('-')->limit(40, '')->value();
    }
}
