<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for operational events that broadcast to a single organization's
 * private Reverb channel (`organizations.{id}`, authorized in routes/channels.php).
 *
 * Concrete events declare their constructor, return the tenant id from
 * {@see organizationId()}, and define the wire contract via broadcastAs() and
 * broadcastWith(). Broadcasting is always queued and dispatched after the
 * surrounding DB transaction commits, so realtime subscribers never see state
 * that a rolled-back transaction never persisted.
 */
abstract class OrganizationBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Queue the broadcast only after the enclosing DB transaction commits.
     */
    public bool $afterCommit = true;

    /**
     * Queue the broadcast job runs on. Null → the default queue. Time-critical
     * events (e.g. an emergency) override this to a prioritised queue so they
     * are not stuck behind a backlog of routine broadcasts.
     */
    public ?string $broadcastQueue = null;

    /**
     * The organization (tenant) whose channel this event broadcasts on.
     */
    abstract public function organizationId(): string;

    /**
     * Stable, client-facing event name (decoupled from the PHP class name).
     */
    abstract public function broadcastAs(): string;

    /**
     * The wire payload delivered to subscribers.
     *
     * @return array<string, mixed>
     */
    abstract public function broadcastWith(): array;

    /**
     * Route the event to role-scoped channels based on its dotted action prefix,
     * so each consumer set only receives what it is authorized for (see
     * routes/channels.php). Unknown prefixes fall back to the general
     * organization channel (members only).
     *
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        $org = $this->organizationId();
        $prefix = explode('.', $this->broadcastAs(), 2)[0];

        return match ($prefix) {
            'incident' => [
                new PrivateChannel("organizations.{$org}.incidents"),
                new PrivateChannel("organizations.{$org}.dashboard"),
            ],
            'assignment' => [
                new PrivateChannel("organizations.{$org}.assignments"),
                new PrivateChannel("organizations.{$org}.dashboard"),
            ],
            'responder' => [
                new PresenceChannel("organizations.{$org}.responders"),
                new PrivateChannel("organizations.{$org}.dashboard"),
            ],
            default => [
                new PrivateChannel("organizations.{$org}"),
            ],
        };
    }
}

