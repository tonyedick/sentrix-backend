<?php

declare(strict_types=1);

namespace App\Domains\Notification\Providers;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Incident\Events\IncidentEscalated;
use App\Domains\Notification\Listeners\NotifyRespondersOfEmergency;
use App\Domains\Notification\Listeners\NotifyRespondersOfIncidentEscalation;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class NotificationServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Cross-domain: fan operational events out to the relevant responders.
        Event::listen(EmergencyTriggered::class, NotifyRespondersOfEmergency::class);
        Event::listen(IncidentEscalated::class, NotifyRespondersOfIncidentEscalation::class);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
