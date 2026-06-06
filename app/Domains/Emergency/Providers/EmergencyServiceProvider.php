<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Providers;

use App\Domains\Emergency\Listeners\RaiseEmergencyForLostContact;
use App\Domains\Emergency\Listeners\RaiseEmergencyForOverdueTrip;
use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Domains\Trip\Events\TripLostContact;
use App\Domains\Trip\Events\TripMarkedOverdue;
use Illuminate\Support\Facades\Event;

final class EmergencyServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Cross-domain escalation: an overdue trip, or one that has gone dark,
        // auto-raises an emergency.
        Event::listen(TripMarkedOverdue::class, RaiseEmergencyForOverdueTrip::class);
        Event::listen(TripLostContact::class, RaiseEmergencyForLostContact::class);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
