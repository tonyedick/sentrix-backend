<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Events;

use App\Domains\Ingest\Models\DetectionEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A detection/signal was ingested and assessed by the pipeline. A lightweight,
 * plain domain event other domains (Core push, analytics) can listen on. The
 * heavy cross-domain effect — opening an Incident — is done explicitly by the
 * IngestService via the Incident domain's IncidentOpened event, not here.
 */
final class DetectionIngested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DetectionEvent $detectionEvent,
        public readonly ?string $incidentId = null,
    ) {}
}
