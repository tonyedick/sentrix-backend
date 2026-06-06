<?php

declare(strict_types=1);

namespace App\Domains\Incident\Listeners;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use App\Domains\Incident\Services\IncidentService;
use App\Domains\Shared\Listeners\QueuedListener;

/**
 * Escalation: when an emergency is triggered at `critical` severity, open a
 * linked incident so the response is tracked through a structured workflow.
 * Queued on the critical queue and idempotent per emergency.
 */
final class OpenIncidentForCriticalEmergency extends QueuedListener
{
    public string $queue = 'critical';

    public function __construct(private readonly IncidentService $incidents) {}

    public function handle(EmergencyTriggered $event): void
    {
        if (! config('sentrix.escalation.auto_incident_for_critical_emergency', true)) {
            return;
        }

        $emergency = $event->record;

        if ($emergency instanceof Emergency && $emergency->severity === EmergencySeverity::Critical) {
            $this->incidents->openFromEmergency($emergency);
        }
    }
}
