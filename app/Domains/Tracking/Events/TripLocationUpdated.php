<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Events;

use App\Domains\Shared\Events\OrganizationBroadcastEvent;

/**
 * A trip's live position moved. Broadcast (coalesced) to the organization channel
 * for the dispatcher map. Deliberately NOT audited — position updates are
 * high-frequency telemetry, not state transitions — and carries scalars only
 * (no model) to keep the hot path light.
 */
final class TripLocationUpdated extends OrganizationBroadcastEvent
{
    public function __construct(
        public readonly string $organization,
        public readonly string $tripId,
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
        return 'trip.location';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'recorded_at' => $this->recordedAt,
            'speed' => $this->speed,
            'heading' => $this->heading,
        ];
    }
}
