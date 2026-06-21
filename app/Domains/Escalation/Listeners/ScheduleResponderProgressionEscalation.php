<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Listeners;

use App\Domains\Assignment\Events\ResponderAcceptedAssignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Escalation\Jobs\EscalateStalledResponder;
use App\Domains\Escalation\Services\EscalationPolicyResolver;

/**
 * When a responder accepts, schedule the delayed no-progression escalation job
 * using the organization's policy threshold (if responder escalation is enabled).
 */
final class ScheduleResponderProgressionEscalation
{
    public function __construct(private readonly EscalationPolicyResolver $policies) {}

    public function handle(ResponderAcceptedAssignment $event): void
    {
        $line = $event->record;

        if (! $line instanceof AssignmentResponder) {
            return;
        }

        $policy = $this->policies->for((string) $line->organization_id);

        if (! $policy->responder_escalation_enabled) {
            return;
        }

        EscalateStalledResponder::dispatch((string) $line->getKey())
            ->delay(now()->addSeconds($policy->responder_no_progression_seconds));
    }
}
