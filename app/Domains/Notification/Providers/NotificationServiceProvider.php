<?php

declare(strict_types=1);

namespace App\Domains\Notification\Providers;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Incident\Events\IncidentEscalated;
use App\Domains\Notification\Contracts\PushProvider;
use App\Domains\Notification\Contracts\SmsProvider;
use App\Domains\Notification\Listeners\NotifyRespondersOfEmergency;
use App\Domains\Notification\Listeners\NotifyRespondersOfIncidentEscalation;
use App\Domains\Notification\Listeners\NotifySafetyContactsOfEmergency;
use App\Domains\Notification\Listeners\RecordNotificationDelivery;
use App\Domains\Notification\Providers\Push\LogPushProvider;
use App\Domains\Notification\Providers\Sms\LogSmsProvider;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

final class NotificationServiceProvider extends DomainServiceProvider
{
    public function register(): void
    {
        // Provider switching: bind the SMS/push provider selected by config. Add a
        // match arm + driver class to introduce a real gateway (Twilio, FCM, …).
        $this->app->bind(SmsProvider::class, static fn (): SmsProvider => match ((string) config('sentrix.notifications.sms.provider', 'log')) {
            default => new LogSmsProvider(),
        });

        $this->app->bind(PushProvider::class, static fn (): PushProvider => match ((string) config('sentrix.notifications.push.provider', 'log')) {
            default => new LogPushProvider(),
        });
    }

    public function boot(): void
    {
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();

        // Cross-domain: fan operational events out to the relevant responders.
        Event::listen(EmergencyTriggered::class, NotifyRespondersOfEmergency::class);
        // …and alert the triggering user's personal safety contacts.
        Event::listen(EmergencyTriggered::class, NotifySafetyContactsOfEmergency::class);
        Event::listen(IncidentEscalated::class, NotifyRespondersOfIncidentEscalation::class);

        // Record per-channel delivery outcomes (attempts/status/failures) for every
        // notification, regardless of channel.
        Event::listen(NotificationSending::class, [RecordNotificationDelivery::class, 'sending']);
        Event::listen(NotificationSent::class, [RecordNotificationDelivery::class, 'sent']);
        Event::listen(NotificationFailed::class, [RecordNotificationDelivery::class, 'failed']);
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }
}
