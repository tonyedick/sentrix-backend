<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Support;

use App\Domains\Incident\Models\Incident;
use App\Domains\Ingest\Models\DetectionEvent;

/**
 * The outcome of an ingest call: the persisted DetectionEvent and, when the
 * decision triggered, the Incident that was opened.
 */
final readonly class IngestResult
{
    public function __construct(
        public DetectionEvent $detectionEvent,
        public ?Incident $incident,
    ) {}
}
