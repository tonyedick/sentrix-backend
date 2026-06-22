<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Providers;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Shared\Providers\DomainServiceProvider;
use App\Domains\Webhooks\Listeners\DispatchWebhooksForEvent;
use Illuminate\Support\Facades\Event;

final class WebhooksServiceProvider extends DomainServiceProvider
{
    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Cross-domain: fan operational events out to subscribed partner endpoints.
        // The listener resolves the event's organization + dotted key and enqueues
        // a DeliverWebhook job per matching active webhook (HTTP happens in the job).
        Event::listen(IncidentOpened::class, DispatchWebhooksForEvent::class);
        Event::listen(EmergencyTriggered::class, DispatchWebhooksForEvent::class);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
