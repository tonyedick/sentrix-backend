<?php

declare(strict_types=1);

namespace App\Domains\Retention\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A batch of observations was archived (sealed) into a downloadable export
 * manifest — the "archive-first" step that licenses later deletion. The record
 * is the RetentionExport row; the archived count lives in the context.
 */
final class EvidenceArchived extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'evidence.archived';
    }
}
