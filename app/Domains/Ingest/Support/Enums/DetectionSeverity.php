<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Support\Enums;

use App\Domains\Incident\Support\Enums\IncidentSeverity;

/**
 * The severity the decision engine assigns to a detection. A superset of
 * IncidentSeverity: it adds `Info` for the lowest, log-only band that never
 * opens an incident. {@see toIncidentSeverity()} maps it down for incident
 * creation.
 */
enum DetectionSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    /**
     * Whether this severity warrants opening an incident (medium and above).
     */
    public function isActionable(): bool
    {
        return match ($this) {
            self::Critical, self::High, self::Medium => true,
            self::Low, self::Info => false,
        };
    }

    /**
     * Map down to the incident severity scale (info → low).
     */
    public function toIncidentSeverity(): IncidentSeverity
    {
        return match ($this) {
            self::Critical => IncidentSeverity::Critical,
            self::High => IncidentSeverity::High,
            self::Medium => IncidentSeverity::Medium,
            self::Low, self::Info => IncidentSeverity::Low,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
