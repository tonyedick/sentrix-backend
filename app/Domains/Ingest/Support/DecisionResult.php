<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Support;

use App\Domains\Ingest\Support\Enums\DetectionSeverity;

/**
 * The immutable output of the DecisionEngine for one detection: the scored risk
 * (0-100 int), the assigned severity, and whether it should open an incident.
 */
final readonly class DecisionResult
{
    public function __construct(
        public int $riskScore,
        public DetectionSeverity $severity,
        public bool $triggered,
    ) {}
}
