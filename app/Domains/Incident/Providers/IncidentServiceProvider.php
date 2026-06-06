<?php

declare(strict_types=1);

namespace App\Domains\Incident\Providers;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Incident\Listeners\OpenIncidentForCriticalEmergency;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class IncidentServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Cross-domain escalation: a critical emergency auto-opens an incident.
        Event::listen(EmergencyTriggered::class, OpenIncidentForCriticalEmergency::class);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
