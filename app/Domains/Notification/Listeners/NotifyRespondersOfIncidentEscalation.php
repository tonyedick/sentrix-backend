<?php

declare(strict_types=1);

namespace App\Domains\Notification\Listeners;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Incident\Events\IncidentEscalated;
use App\Domains\Incident\Models\Incident;
use App\Domains\Notification\Notifications\IncidentEscalatedNotification;
use App\Domains\Notification\Services\ResponderResolver;
use App\Domains\Shared\Listeners\QueuedListener;
use Illuminate\Support\Facades\Notification;

/**
 * Pages the organization's incident coordinators (anyone who can escalate an
 * incident) when one is escalated, excluding whoever performed the escalation.
 */
final class NotifyRespondersOfIncidentEscalation extends QueuedListener
{
    public string $queue = 'critical';

    public function __construct(private readonly ResponderResolver $responders) {}

    public function handle(IncidentEscalated $event): void
    {
        $incident = $event->record;

        if (! $incident instanceof Incident) {
            return;
        }

        $recipients = $this->responders->withPermission(
            $incident->organization,
            DefaultPermission::IncidentsEscalate->value,
            $event->actorId,
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new IncidentEscalatedNotification($incident));
        }
    }
}
