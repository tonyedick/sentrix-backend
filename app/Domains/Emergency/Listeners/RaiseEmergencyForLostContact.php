<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Listeners;

use App\Domains\Emergency\Services\EmergencyService;
use App\Domains\Shared\Listeners\QueuedListener;
use App\Domains\Trip\Events\TripLostContact;
use App\Domains\Trip\Models\Trip;

/**
 * Escalation: when an active trip's device goes dark, raise a "lost contact"
 * emergency. Queued on the critical queue and idempotent (the service is a no-op
 * if the trip already has a live emergency — so a trip that is both overdue and
 * dark produces a single emergency).
 */
final class RaiseEmergencyForLostContact extends QueuedListener
{
    public string $queue = 'critical';

    public function __construct(private readonly EmergencyService $emergencies) {}

    public function handle(TripLostContact $event): void
    {
        if (! config('sentrix.escalation.auto_emergency_on_lost_contact', true)) {
            return;
        }

        if ($event->record instanceof Trip) {
            $this->emergencies->raiseForTrip($event->record, 'trip.lost_contact', 'Lost contact — no recent location update.');
        }
    }
}
