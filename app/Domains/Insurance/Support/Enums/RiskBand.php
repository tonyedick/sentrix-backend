<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Support\Enums;

/**
 * Coarse risk classification derived from a 0-100 risk score. Drives premium
 * pricing: a lower band means a lower multiplier on the baseline premium.
 *
 *   low      → strong security posture, few recent incidents.
 *   moderate → mixed signals.
 *   high     → thin protective coverage and/or many recent incidents.
 */
enum RiskBand: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';

    /**
     * Classify a 0-100 risk score into a band. Deterministic thresholds.
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score < 34 => self::Low,
            $score < 67 => self::Moderate,
            default => self::High,
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
