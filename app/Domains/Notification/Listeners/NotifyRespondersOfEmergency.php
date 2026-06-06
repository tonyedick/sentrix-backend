<?php

declare(strict_types=1);

namespace App\Domains\Notification\Listeners;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Notification\Notifications\EmergencyTriggeredNotification;
use App\Domains\Notification\Services\ResponderResolver;
use App\Domains\Shared\Listeners\QueuedListener;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the organization's responders (anyone who can acknowledge an
 * emergency) when one is triggered, excluding the person who raised it. Queued
 * on the critical queue; the notifications themselves are queued + retried.
 *
 * At-least-once by design: a retry after a partial failure may re-notify some
 * recipients. For life-safety alerts a duplicate is preferable to a miss.
 */
final class NotifyRespondersOfEmergency extends QueuedListener
{
    public string $queue = 'critical';

    public function __construct(private readonly ResponderResolver $responders) {}

    public function handle(EmergencyTriggered $event): void
    {
        $emergency = $event->record;

        if (! $emergency instanceof Emergency) {
            return;
        }

        $recipients = $this->responders->withPermission(
            $emergency->organization,
            DefaultPermission::EmergenciesAcknowledge->value,
            $emergency->user_id,
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new EmergencyTriggeredNotification($emergency));
        }
    }
}
