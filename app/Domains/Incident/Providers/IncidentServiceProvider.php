<?php

declare(strict_types=1);

namespace App\Domains\Incident\Providers;

use App\Domains\Assignment\Events\AssignmentCancelled;
use App\Domains\Assignment\Events\AssignmentCompleted;
use App\Domains\Assignment\Events\AssignmentCreated;
use App\Domains\Assignment\Events\AssignmentDispatchEscalated;
use App\Domains\Assignment\Events\ResponderAcceptedAssignment;
use App\Domains\Assignment\Events\ResponderAssignmentCompleted;
use App\Domains\Assignment\Events\ResponderAssignmentTimedOut;
use App\Domains\Assignment\Events\ResponderDeclinedAssignment;
use App\Domains\Assignment\Events\ResponderEnRoute;
use App\Domains\Assignment\Events\ResponderOffered;
use App\Domains\Assignment\Events\ResponderOnScene;
use App\Domains\Assignment\Events\ResponderStoodDown;
use App\Domains\Incident\Console\Commands\BackfillIncidentTimeline;
use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Incident\Events\IncidentClosed;
use App\Domains\Incident\Events\IncidentEscalated;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Incident\Events\IncidentResolved;
use App\Domains\Incident\Events\IncidentStatusChanged;
use App\Domains\Incident\Listeners\OpenIncidentForCriticalEmergency;
use App\Domains\Incident\Listeners\RecordTimelineEntryFromDomainEvent;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class IncidentServiceProvider extends DomainServiceProvider
{
    /**
     * Operational events projected onto the incident timeline
     * (incident_timeline_entries) by RecordTimelineEntryFromDomainEvent.
     * Synchronous, in-transaction — see the listener.
     *
     * @var list<class-string>
     */
    private const TIMELINE_EVENTS = [
        // Incident lifecycle.
        IncidentOpened::class,
        IncidentStatusChanged::class,
        IncidentEscalated::class,
        IncidentResolved::class,
        IncidentClosed::class,
        // Assignment + dispatch lifecycle.
        AssignmentCreated::class,
        ResponderOffered::class,
        ResponderAcceptedAssignment::class,
        ResponderDeclinedAssignment::class,
        ResponderAssignmentTimedOut::class,
        ResponderStoodDown::class,
        ResponderEnRoute::class,
        ResponderOnScene::class,
        ResponderAssignmentCompleted::class,
        AssignmentCancelled::class,
        AssignmentCompleted::class,
        AssignmentDispatchEscalated::class,
    ];

    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Cross-domain escalation: a critical emergency auto-opens an incident.
        Event::listen(EmergencyTriggered::class, OpenIncidentForCriticalEmergency::class);

        foreach (self::TIMELINE_EVENTS as $event) {
            Event::listen($event, RecordTimelineEntryFromDomainEvent::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillIncidentTimeline::class]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
