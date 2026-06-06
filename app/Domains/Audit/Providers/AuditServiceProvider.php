<?php

declare(strict_types=1);

namespace App\Domains\Audit\Providers;

use App\Domains\Audit\Listeners\RecordAuditTrail;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Support\Facades\Event;

final class AuditServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Record an audit row for every dispatched event implementing Auditable.
        Event::listen('*', [RecordAuditTrail::class, 'handle']);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
