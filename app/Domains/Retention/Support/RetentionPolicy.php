<?php

declare(strict_types=1);

namespace App\Domains\Retention\Support;

use App\Domains\Organization\Models\Organization;

/**
 * Immutable, resolved retention policy for one subscription plan: the cumulative
 * tier windows (days), evidence retention, and storage quota. Built from the
 * `sentrix.retention` config and mirrors the Sentrix Omni retention engine.
 *
 * Plan resolution: an organization's plan is read from its `plan` attribute when
 * present, else falls back to `sentrix.retention.default_plan`. The Organization
 * model has no `plan` column today, so every org currently resolves to the
 * default plan — the lookup is forward-compatible for when one is added.
 */
final readonly class RetentionPolicy
{
    public function __construct(
        public string $plan,
        public int $hotDays,
        public int $warmDays,
        public int $coldDays,
        public int $evidenceDays,
        public int $quotaGb,
    ) {}

    /**
     * Resolve the policy for an organization (plan attribute -> default plan).
     */
    public static function forOrganization(Organization $organization): self
    {
        $plan = self::resolvePlan($organization);

        return self::forPlan($plan);
    }

    /**
     * Build a policy from a plan key, falling back to the configured default plan
     * when the key is unknown.
     */
    public static function forPlan(string $plan): self
    {
        /** @var array<string, array<string, int>> $plans */
        $plans = (array) config('sentrix.retention.plans', []);

        if (! array_key_exists($plan, $plans)) {
            $plan = self::defaultPlan();
        }

        /** @var array<string, int> $config */
        $config = $plans[$plan] ?? [];

        return new self(
            plan: $plan,
            hotDays: (int) ($config['hot_days'] ?? 0),
            warmDays: (int) ($config['warm_days'] ?? 0),
            coldDays: (int) ($config['cold_days'] ?? 0),
            evidenceDays: (int) ($config['evidence_days'] ?? 0),
            quotaGb: (int) ($config['quota_gb'] ?? 0),
        );
    }

    /**
     * The resolved plan key for an organization. Reads the model's `plan`
     * attribute when it carries a non-empty string, else the default plan.
     */
    public static function resolvePlan(Organization $organization): string
    {
        $plan = $organization->getAttribute('plan');

        if (is_string($plan) && $plan !== '') {
            return $plan;
        }

        return self::defaultPlan();
    }

    public static function defaultPlan(): string
    {
        return (string) config('sentrix.retention.default_plan', 'business');
    }

    /**
     * Cumulative upper bound (in days) of the hot tier.
     */
    public function hotCeiling(): int
    {
        return $this->hotDays;
    }

    /**
     * Cumulative upper bound (in days) of the warm tier.
     */
    public function warmCeiling(): int
    {
        return $this->hotDays + $this->warmDays;
    }

    /**
     * Cumulative upper bound (in days) of the cold tier. Observations older than
     * this stay cold and become archive-eligible.
     */
    public function coldCeiling(): int
    {
        return $this->hotDays + $this->warmDays + $this->coldDays;
    }
}
