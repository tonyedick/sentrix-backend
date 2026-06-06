<?php

declare(strict_types=1);

namespace App\Domains\Shared\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for queued domain listeners. Centralises the retry/observability policy
 * mandated by docs/event-system.md so individual listeners only carry their
 * business logic:
 *
 *   - bounded retries with exponential-ish backoff (no infinite loops);
 *   - a circuit-breaker on repeated exceptions (`maxExceptions`);
 *   - a wall-clock timeout; and
 *   - structured failure logging (no silent failures).
 *
 * Handlers must remain idempotent — a job may run more than once across retries.
 */
abstract class QueuedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /** Maximum attempts before the job is sent to the failed-jobs table. */
    public int $tries = 5;

    /** Stop retrying after this many uncaught exceptions, even if attempts remain. */
    public int $maxExceptions = 3;

    /** Hard per-attempt timeout (seconds). */
    public int $timeout = 60;

    /**
     * Backoff (seconds) between successive attempts.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    /**
     * Final-failure hook. Preserve context for operational debugging.
     */
    public function failed(object $event, Throwable $exception): void
    {
        Log::error(static::class.' failed after exhausting retries', [
            'listener' => static::class,
            'event' => $event::class,
            'exception' => $exception->getMessage(),
        ]);
    }
}
