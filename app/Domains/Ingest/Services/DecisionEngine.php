<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Services;

use App\Domains\Ingest\Support\DecisionResult;
use App\Domains\Ingest\Support\Enums\DetectionSeverity;
use Illuminate\Support\Str;

/**
 * Sentrix's deterministic detection-scoring brain — the Laravel port of Omni's
 * SentrixOmni.decide(). It maps a detection type/label to a base risk and a
 * severity band, scales the risk by the detector's confidence, and decides
 * whether the signal is actionable (opens an incident) or log-only.
 *
 * Deterministic and side-effect-free: same input → same output, no I/O. The
 * IngestService owns persistence and incident creation; this class only judges.
 *
 * Scoring table (base risk on a 0-100 scale, before confidence scaling):
 *
 *   critical (~85-90): weapon/gun/knife/weapon_detected, fire/smoke/fire_smoke,
 *                      gunshot/scream/distress/sos
 *   high     (~70-75): intrusion/perimeter_breach/unauthorized_access,
 *                      crowd_surge, fall/fall_detected
 *   medium   (~50-55): loitering, abandoned_object
 *   low/info (~20)   : everything else
 *
 * Trigger rule: triggered when the severity is medium|high|critical (i.e. the
 * scaled risk is >= 40). Below that is logged only.
 */
final readonly class DecisionEngine
{
    /**
     * The lowest scaled risk that opens an incident. Aligns with the medium band.
     */
    private const TRIGGER_THRESHOLD = 40;

    /**
     * Assess a detection: returns the risk score (0-100), severity, and whether
     * it should open an incident.
     *
     * @param  float|null  $confidence  detector confidence 0..1 (default 1.0)
     */
    public function assess(?string $type, ?float $confidence = null): DecisionResult
    {
        [$baseRisk, $severity] = $this->classify($type);

        $confidence = $this->normalizeConfidence($confidence);

        // Scale the base risk by confidence, then re-derive the severity band so
        // a low-confidence weapon hit can fall out of "critical" — matching the
        // intent of Omni's confidence gate.
        $risk = (int) round($baseRisk * $confidence);
        $risk = max(0, min(100, $risk));

        $effectiveSeverity = $this->severityForRisk($risk, $severity);

        $triggered = $effectiveSeverity->isActionable() || $risk >= self::TRIGGER_THRESHOLD;

        return new DecisionResult($risk, $effectiveSeverity, $triggered);
    }

    /**
     * Map a detection type/label to its base risk and intrinsic severity band.
     *
     * @return array{0: int, 1: DetectionSeverity}
     */
    private function classify(?string $type): array
    {
        $label = Str::lower(trim((string) $type));

        return match (true) {
            $this->matches($label, ['weapon', 'gun', 'firearm', 'knife', 'weapon_detected'])
                => [90, DetectionSeverity::Critical],
            $this->matches($label, ['gunshot', 'scream', 'distress', 'sos'])
                => [88, DetectionSeverity::Critical],
            $this->matches($label, ['fire', 'smoke', 'fire_smoke'])
                => [85, DetectionSeverity::Critical],
            $this->matches($label, ['intrusion', 'perimeter_breach', 'perimeter', 'unauthorized_access', 'breach'])
                => [75, DetectionSeverity::High],
            $this->matches($label, ['crowd_surge', 'crowd', 'stampede'])
                => [72, DetectionSeverity::High],
            $this->matches($label, ['fall', 'fall_detected'])
                => [70, DetectionSeverity::High],
            $this->matches($label, ['loiter', 'loitering'])
                => [55, DetectionSeverity::Medium],
            $this->matches($label, ['abandoned_object', 'abandoned', 'unattended'])
                => [50, DetectionSeverity::Medium],
            default
                => [20, DetectionSeverity::Low],
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $label, array $needles): bool
    {
        if ($label === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if (str_contains($label, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Re-derive the severity band from the (confidence-scaled) risk, never
     * exceeding the type's intrinsic ceiling. Keeps risk and severity coherent.
     */
    private function severityForRisk(int $risk, DetectionSeverity $ceiling): DetectionSeverity
    {
        $banded = match (true) {
            $risk >= 85 => DetectionSeverity::Critical,
            $risk >= 70 => DetectionSeverity::High,
            $risk >= self::TRIGGER_THRESHOLD => DetectionSeverity::Medium,
            $risk >= 20 => DetectionSeverity::Low,
            default => DetectionSeverity::Info,
        };

        // Never escalate above the type's intrinsic band.
        return $this->rank($banded) > $this->rank($ceiling) ? $ceiling : $banded;
    }

    private function rank(DetectionSeverity $severity): int
    {
        return match ($severity) {
            DetectionSeverity::Info => 0,
            DetectionSeverity::Low => 1,
            DetectionSeverity::Medium => 2,
            DetectionSeverity::High => 3,
            DetectionSeverity::Critical => 4,
        };
    }

    private function normalizeConfidence(?float $confidence): float
    {
        if ($confidence === null) {
            return 1.0;
        }

        return max(0.0, min(1.0, $confidence));
    }
}
