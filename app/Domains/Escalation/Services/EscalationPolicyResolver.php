<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Services;

use App\Domains\Escalation\Models\EscalationPolicy;

/**
 * Resolves the effective escalation policy for an organization: the saved
 * escalation_policies row, or a transient instance populated from the
 * sentrix.escalation.* config defaults when none exists. Callers always get a
 * usable EscalationPolicy without null checks.
 */
final readonly class EscalationPolicyResolver
{
    public function for(string $organizationId): EscalationPolicy
    {
        return EscalationPolicy::query()
            ->where('organization_id', $organizationId)
            ->first()
            ?? $this->default($organizationId);
    }

    private function default(string $organizationId): EscalationPolicy
    {
        // Not persisted — config-backed defaults for orgs without a policy row.
        return new EscalationPolicy([
            'organization_id' => $organizationId,
            'incident_unassigned_seconds' => (int) config('sentrix.escalation.incident_unassigned_seconds', 300),
            'assignment_unaccepted_seconds' => (int) config('sentrix.escalation.assignment_unaccepted_seconds', 120),
            'responder_no_progression_seconds' => (int) config('sentrix.escalation.responder_no_progression_seconds', 600),
            'incident_escalation_enabled' => (bool) config('sentrix.escalation.incident_escalation_enabled', true),
            'assignment_escalation_enabled' => (bool) config('sentrix.escalation.assignment_escalation_enabled', true),
            'responder_escalation_enabled' => (bool) config('sentrix.escalation.responder_escalation_enabled', true),
        ]);
    }
}
