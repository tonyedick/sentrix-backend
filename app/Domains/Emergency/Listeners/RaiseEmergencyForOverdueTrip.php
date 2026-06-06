<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Listeners;

use App\Domains\Emergency\Services\EmergencyService;
use App\Domains\Shared\Listeners\QueuedListener;
use App\Domains\Trip\Events\TripMarkedOverdue;
use App\Domains\Trip\Models\Trip;

/**
 * Escalation: when a trip is flagged overdue, automatically raise an emergency
 * for the monitored user. Queued on the critical queue and idempotent (the
 * service is a no-op if the trip already has a live emergency), so retries and
 * repeated sweeps never produce duplicates.
 */
final class RaiseEmergencyForOverdueTrip extends QueuedListener
{
    /** Route this safety-critical escalation ahead of routine work. */
    public string $queue = 'critical';

    public function __construct(private readonly EmergencyService $emergencies) {}

    public function handle(TripMarkedOverdue $event): void
    {
        if (! config('sentrix.escalation.auto_emergency_on_overdue_trip', true)) {
            return;
        }

        if ($event->record instanceof Trip) {
            $this->emergencies->raiseForOverdueTrip($event->record);
        }
    }
}
