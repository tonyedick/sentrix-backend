<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Providers;

use App\Domains\Assignment\Console\Commands\EscalateOverdueAssignments;
use App\Domains\Assignment\Console\Commands\ReconcileResponderConnectivity;
use App\Domains\Assignment\Events\AssignmentCreated;
use App\Domains\Assignment\Events\AssignmentDispatchEscalated;
use App\Domains\Assignment\Events\ResponderAssignmentTimedOut;
use App\Domains\Assignment\Events\ResponderDeclinedAssignment;
use App\Domains\Assignment\Events\ResponderOffered;
use App\Domains\Assignment\Listeners\NotifyDispatchersOfEscalation;
use App\Domains\Assignment\Listeners\NotifyResponderOfAssignment;
use App\Domains\Assignment\Listeners\QueueAutoDispatch;
use App\Domains\Assignment\Listeners\QueueDispatchRecommendation;
use App\Domains\Assignment\Listeners\QueueReassignmentOnFailedOffer;
use App\Domains\Assignment\Listeners\ReleaseAssignmentOnIncidentClosure;
use App\Domains\Incident\Events\IncidentClosed;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Incident\Events\IncidentResolved;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class AssignmentServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Page the dispatched responder.
        Event::listen(ResponderOffered::class, NotifyResponderOfAssignment::class);

        // Advisory AI dispatch recommendation. Listen to IncidentOpened ONLY (the
        // incident is the assignable unit) — listening to EmergencyTriggered too
        // would double-recommend for critical emergencies (event-map §10.8).
        Event::listen(IncidentOpened::class, QueueDispatchRecommendation::class);

        // Close the loop: resolving/closing an incident releases its responders.
        Event::listen(IncidentResolved::class, ReleaseAssignmentOnIncidentClosure::class);
        Event::listen(IncidentClosed::class, ReleaseAssignmentOnIncidentClosure::class);

        // Auto-reassignment: a declined/timed-out offer for a still-needed role
        // re-offers the next-best candidate (or escalates).
        Event::listen(ResponderDeclinedAssignment::class, QueueReassignmentOnFailedOffer::class);
        Event::listen(ResponderAssignmentTimedOut::class, QueueReassignmentOnFailedOffer::class);

        // Auto-dispatch: an assignment opened in auto mode fills itself.
        Event::listen(AssignmentCreated::class, QueueAutoDispatch::class);

        // Page dispatchers when dispatch escalates.
        Event::listen(AssignmentDispatchEscalated::class, NotifyDispatchersOfEscalation::class);

        if ($this->app->runningInConsole()) {
            $this->commands([EscalateOverdueAssignments::class, ReconcileResponderConnectivity::class]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
