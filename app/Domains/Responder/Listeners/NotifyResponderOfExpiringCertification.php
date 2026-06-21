<?php

declare(strict_types=1);

namespace App\Domains\Responder\Listeners;

use App\Domains\Responder\Events\ResponderCertificationExpiring;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Notifications\CertificationExpiringNotification;
use App\Domains\Shared\Listeners\QueuedListener;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies a responder when one of their certifications is nearing expiry.
 * Queued and idempotent (the sweep only emits the event once per certification).
 */
final class NotifyResponderOfExpiringCertification extends QueuedListener
{
    public function handle(ResponderCertificationExpiring $event): void
    {
        $responder = $event->record;

        if (! $responder instanceof Responder) {
            return;
        }

        $responder->loadMissing('user');

        if ($responder->user === null) {
            return;
        }

        Notification::send(
            $responder->user,
            new CertificationExpiringNotification(
                (string) ($event->context['name'] ?? 'Certification'),
                isset($event->context['expires_at']) ? (string) $event->context['expires_at'] : null,
            ),
        );
    }
}
