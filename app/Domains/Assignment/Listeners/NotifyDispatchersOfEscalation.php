<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Listeners;

use App\Domains\Assignment\Events\AssignmentDispatchEscalated;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Notifications\AssignmentEscalatedNotification;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Notification\Services\ResponderResolver;
use App\Domains\Shared\Listeners\QueuedListener;
use Illuminate\Support\Facades\Notification;

/**
 * Pages the organization's dispatchers (anyone who can dispatch) when an
 * assignment is escalated. Queued on the critical queue; idempotent.
 */
final class NotifyDispatchersOfEscalation extends QueuedListener
{
    public string $queue = 'critical';

    public function __construct(private readonly ResponderResolver $responders) {}

    public function handle(AssignmentDispatchEscalated $event): void
    {
        $assignment = $event->record;

        if (! $assignment instanceof Assignment) {
            return;
        }

        $assignment->loadMissing('organization');

        if ($assignment->organization === null) {
            return;
        }

        $recipients = $this->responders->withPermission(
            $assignment->organization,
            DefaultPermission::AssignmentsDispatch->value,
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AssignmentEscalatedNotification(
                (string) $assignment->getKey(),
                $assignment->incident_id,
                (string) ($event->context['reason'] ?? 'unfilled'),
                (int) ($event->context['level'] ?? 1),
            ));
        }
    }
}
