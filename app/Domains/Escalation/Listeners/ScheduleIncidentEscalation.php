<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Listeners;

use App\Domains\Escalation\Jobs\EscalateUnassignedIncident;
use App\Domains\Escalation\Services\EscalationPolicyResolver;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Incident\Models\Incident;

/**
 * On incident creation, schedule the delayed incident-escalation job using the
 * organization's policy threshold (if incident escalation is enabled).
 */
final class ScheduleIncidentEscalation
{
    public function __construct(private readonly EscalationPolicyResolver $policies) {}

    public function handle(IncidentOpened $event): void
    {
        $incident = $event->record;

        if (! $incident instanceof Incident) {
            return;
        }

        $policy = $this->policies->for((string) $incident->organization_id);

        if (! $policy->incident_escalation_enabled) {
            return;
        }

        EscalateUnassignedIncident::dispatch((string) $incident->getKey())
            ->delay(now()->addSeconds($policy->incident_unassigned_seconds));
    }
}
