<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A batch of observations was ingested into the vault. To keep the hot ingest
 * path light we emit this ONCE per batch, passing a representative observation
 * as the record and the batch count in context (rather than a per-row event).
 */
final class ObservationsRecorded extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'evidence.observed';
    }
}
