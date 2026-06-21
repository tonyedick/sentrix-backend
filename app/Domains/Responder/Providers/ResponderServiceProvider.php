<?php

declare(strict_types=1);

namespace App\Domains\Responder\Providers;

use App\Domains\Responder\Console\Commands\CheckExpiringCertifications;
use App\Domains\Responder\Console\Commands\ProcessDutyShifts;
use App\Domains\Responder\Events\ResponderCertificationExpiring;
use App\Domains\Responder\Listeners\NotifyResponderOfExpiringCertification;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class ResponderServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        Event::listen(ResponderCertificationExpiring::class, NotifyResponderOfExpiringCertification::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CheckExpiringCertifications::class, ProcessDutyShifts::class]);
        }
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
