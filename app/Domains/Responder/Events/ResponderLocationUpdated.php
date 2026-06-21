<?php

declare(strict_types=1);

namespace App\Domains\Responder\Events;

use App\Domains\Shared\Events\OrganizationBroadcastEvent;

/**
 * A responder's live position moved. Broadcast (coalesced) to the organization
 * channel for the dispatcher map. Deliberately NOT audited — high-frequency
 * telemetry, not a state transition — and carries scalars only to keep the hot
 * path light.
 */
final class ResponderLocationUpdated extends OrganizationBroadcastEvent
{
    public function __construct(
        public readonly string $organization,
        public readonly string $responderId,
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $recordedAt,
        public readonly ?float $speed = null,
        public readonly ?float $heading = null,
    ) {}

    public function organizationId(): string
    {
        return $this->organization;
    }

    public function broadcastAs(): string
    {
        return 'responder.location';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'responder_id' => $this->responderId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'recorded_at' => $this->recordedAt,
            'speed' => $this->speed,
            'heading' => $this->heading,
        ];
    }
}
