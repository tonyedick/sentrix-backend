<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Events;

use App\Domains\Ledger\Models\LedgerSource;
use App\Domains\Ledger\Models\LedgerWrite;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A source reported a write into the Ledger. Plain platform event (NOT an
 * organization record event) — the Ledger is cross-tenant, so it carries no
 * org-record broadcast/audit semantics. Other parts of the platform may listen
 * to fan out to dashboards/notifiers.
 */
final class SourceWriteRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly LedgerSource $source,
        public readonly LedgerWrite $write,
    ) {}
}
