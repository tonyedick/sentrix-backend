<?php

declare(strict_types=1);

namespace App\Domains\Core\Events;

use App\Domains\Core\DTOs\CoreEventData;
use App\Domains\Shared\Events\OrganizationBroadcastEvent;

/**
 * A detection/event posted by a product (or the Core agent) to
 * POST /api/v1/core/events. Broadcast to the organization's private Reverb
 * channel so operator dashboards / the mobile app receive a proactive alert.
 *
 * This is broadcast-only (no model record, no audit row): the bridge stays a
 * thin pass-through. The dotted {@see CoreEventData::$type} (e.g.
 * "omni.weapon_detected") is the client-facing broadcast name; its first
 * segment routes the channel via {@see OrganizationBroadcastEvent::broadcastOn()}
 * (unknown prefixes fall back to the general organization channel — which is
 * what these product events want).
 */
final class CoreEventReceived extends OrganizationBroadcastEvent
{
    public function __construct(
        public readonly CoreEventData $event,
        public readonly string $organizationId,
    ) {}

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function broadcastAs(): string
    {
        return $this->event->type;
    }

    /**
     * Compact wire payload for subscribers.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->event->type,
            'source' => $this->event->source,
            'severity' => $this->event->severity,
            'summary' => $this->event->summary,
            'org' => $this->event->org,
            'site' => $this->event->site,
            'zone' => $this->event->zone,
            'subjects' => $this->event->subjects,
            'location' => $this->event->location,
        ];
    }
}
