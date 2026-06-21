<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Events;

use App\Domains\Ledger\Models\LedgerSource;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dead-man switch: an active source that previously wrote has gone silent past
 * the stale window and has been flagged exactly once. Re-armed by its next
 * write. Plain platform event — the caller publishes to platform dashboards /
 * notifiers.
 */
final class SourceWentStale
{
    use Dispatchable;

    public function __construct(
        public readonly LedgerSource $source,
        public readonly int $silentForMinutes,
    ) {}
}
