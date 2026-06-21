<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Listeners;

use App\Domains\Assignment\Events\ResponderOffered;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Assignment\Notifications\AssignmentOfferedNotification;
use App\Domains\Shared\Listeners\QueuedListener;
use Illuminate\Support\Facades\Notification;

/**
 * Pages the dispatched responder to accept or decline. Queued on the critical
 * queue; idempotent (one offer event per line).
 */
final class NotifyResponderOfAssignment extends QueuedListener
{
    public string $queue = 'critical';

    public function handle(ResponderOffered $event): void
    {
        $line = $event->record;

        if (! $line instanceof AssignmentResponder) {
            return;
        }

        $line->loadMissing('responder.user');
        $user = $line->responder?->user;

        if ($user === null) {
            return;
        }

        Notification::send($user, new AssignmentOfferedNotification(
            (string) $line->getKey(),
            $line->role->value,
            $line->incident_id,
            (string) $line->organization_id,
        ));
    }
}
