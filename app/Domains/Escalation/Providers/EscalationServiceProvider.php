<?php

declare(strict_types=1);

namespace App\Domains\Escalation\Providers;

use App\Domains\Assignment\Events\ResponderAcceptedAssignment;
use App\Domains\Escalation\Listeners\ScheduleIncidentEscalation;
use App\Domains\Escalation\Listeners\ScheduleResponderProgressionEscalation;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

/**
 * The Escalation Engine. Orchestrates time-based escalation across domains via
 * delayed Redis/Horizon jobs governed by per-organization EscalationPolicy:
 *
 *  - Incident: opened → no assignment within threshold → escalate (this domain).
 *  - Responder: accepted → no progression within threshold → escalate (this domain).
 *  - Assignment: created → no acceptance within threshold → escalate (already
 *    implemented in the Assignment domain; governed by its own config).
 *
 * Depends on Incident + Assignment (it triggers their escalations); they do not
 * depend on it.
 */
final class EscalationServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();

        Event::listen(IncidentOpened::class, ScheduleIncidentEscalation::class);
        Event::listen(ResponderAcceptedAssignment::class, ScheduleResponderProgressionEscalation::class);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
