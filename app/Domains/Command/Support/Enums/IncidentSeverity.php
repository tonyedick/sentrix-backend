<?php

declare(strict_types=1);

namespace App\Domains\Command\Support\Enums;

/**
 * Severity of a routed command incident. Drives the SLA clocks (dispatch +
 * on-scene targets). This domain owns its own copy (platform/national-scoped).
 */
enum IncidentSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
