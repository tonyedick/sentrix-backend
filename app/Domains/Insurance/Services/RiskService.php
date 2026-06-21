<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Services;

use App\Domains\Emergency\Models\Emergency;
use App\Domains\Hardware\Models\Device;
use App\Domains\Incident\Models\Incident;
use App\Domains\Insurance\Support\Enums\RiskBand;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Models\Responder;

/**
 * The risk engine — the synergy layer between security posture and insurance
 * pricing. It reads REAL signals from sibling domains (read-only cross-domain
 * reads are intentional here: a risk engine observes the rest of the platform)
 * to produce a deterministic 0-100 risk score, a band, and the contributing
 * factors. A better posture (more protective assets) lowers risk; more recent
 * incidents raise it.
 *
 * The quote then prices an annual premium off that score and reports the saving
 * versus a flat baseline insurer that ignores posture entirely.
 */
final readonly class RiskService
{
    /**
     * Recent activity window (days) for incident/emergency counts.
     */
    private const RECENT_WINDOW_DAYS = 90;

    /**
     * Annual premium of a flat baseline insurer that ignores security posture,
     * in cents. Sentrix prices off this with a risk multiplier; the difference
     * is the member's saving.
     */
    private const BASELINE_PREMIUM_CENTS = 1_200_000; // $12,000 / year

    /**
     * Compute the organization's risk profile from live platform signals.
     *
     * @return array{
     *     score: int,
     *     band: string,
     *     factors: array<string, mixed>
     * }
     */
    public function profile(Organization $organization): array
    {
        $signals = $this->signals($organization);
        $score = $this->score($signals);

        return [
            'score' => $score,
            'band' => RiskBand::fromScore($score)->value,
            'factors' => [
                'devices' => $signals['devices'],
                'responders' => $signals['responders'],
                'recent_incidents' => $signals['recent_incidents'],
                'recent_emergencies' => $signals['recent_emergencies'],
                'window_days' => self::RECENT_WINDOW_DAYS,
            ],
        ];
    }

    /**
     * Price a risk-adjusted annual premium and report the saving versus a flat
     * baseline insurer. Lower risk → lower multiplier → bigger Sentrix saving.
     *
     * @return array{
     *     score: int,
     *     band: string,
     *     currency: string,
     *     baseline_premium_cents: int,
     *     premium_cents: int,
     *     sentrix_saving_cents: int,
     *     factors: array<string, mixed>
     * }
     */
    public function quote(Organization $organization, string $currency = 'USD'): array
    {
        $profile = $this->profile($organization);

        // Risk multiplier spans 0.6x (lowest risk) to 1.5x (highest risk),
        // scaled linearly off the 0-100 score. The baseline insurer is, in
        // effect, a flat 1.0x that ignores posture entirely.
        $multiplier = 0.6 + ($profile['score'] / 100) * 0.9;
        $premiumCents = (int) round(self::BASELINE_PREMIUM_CENTS * $multiplier);
        $savingCents = self::BASELINE_PREMIUM_CENTS - $premiumCents;

        return [
            'score' => $profile['score'],
            'band' => $profile['band'],
            'currency' => $currency,
            'baseline_premium_cents' => self::BASELINE_PREMIUM_CENTS,
            'premium_cents' => $premiumCents,
            // Can be negative for a high-risk org (Sentrix prices above baseline).
            'sentrix_saving_cents' => $savingCents,
            'factors' => $profile['factors'],
        ];
    }

    /**
     * Gather the raw, org-scoped counts the score is built from.
     *
     * @return array{
     *     devices: int,
     *     responders: int,
     *     recent_incidents: int,
     *     recent_emergencies: int
     * }
     */
    private function signals(Organization $organization): array
    {
        $orgId = $organization->getKey();
        $since = now()->subDays(self::RECENT_WINDOW_DAYS);

        return [
            'devices' => Device::query()->where('organization_id', $orgId)->count(),
            'responders' => Responder::query()->where('organization_id', $orgId)->count(),
            'recent_incidents' => Incident::query()
                ->where('organization_id', $orgId)
                ->where('created_at', '>=', $since)
                ->count(),
            'recent_emergencies' => Emergency::query()
                ->where('organization_id', $orgId)
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * Deterministic 0-100 risk score.
     *
     * Start from a neutral midpoint, then:
     *   - subtract for protective assets (registered hardware devices and
     *     responders) — more coverage lowers risk, with diminishing returns
     *     capped so a single asset class can't zero out the score; and
     *   - add for recent incidents/emergencies — recent trouble raises risk.
     *
     * The result is clamped to [0, 100]. Intentionally simple, but real and
     * reproducible from the same inputs.
     *
     * @param  array{devices: int, responders: int, recent_incidents: int, recent_emergencies: int}  $signals
     */
    private function score(array $signals): int
    {
        $score = 50; // neutral starting posture

        // Protective assets reduce risk (each capped to avoid runaway credit).
        $score -= min($signals['devices'] * 3, 20);      // up to -20 for hardware
        $score -= min($signals['responders'] * 4, 20);   // up to -20 for responders

        // Recent operational trouble increases risk.
        $score += min($signals['recent_incidents'] * 6, 30);    // up to +30
        $score += min($signals['recent_emergencies'] * 5, 25);  // up to +25

        return max(0, min(100, $score));
    }
}
