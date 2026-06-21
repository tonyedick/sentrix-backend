<?php

declare(strict_types=1);

namespace App\Domains\Rides\Events;

use App\Domains\Rides\Models\Ride;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ride-aware SOS: the rider raised a panic during a ride. Carries the ride id,
 * the matched-driver snapshot, and the last known position so a listener can fan
 * the alert out to the response network + guardians.
 *
 * For now this is record + event only. LATER this bridges to the Emergency domain
 * (and Core events) — a listener there will mint an EmergencyEvent / signal-ingest
 * from this payload, the same way safety.py's /sos fans out to signal_ingest +
 * core_event today. User-scoped — NOT an OrganizationRecordEvent.
 */
final class RideSosTriggered
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array{driver_id:?string,driver_name:?string,driver_plate:?string}  $driver
     * @param  array{lat:float|string|null,lng:float|string|null}  $position
     */
    public function __construct(
        public readonly string $rideId,
        public readonly array $driver,
        public readonly array $position,
    ) {}

    public static function fromRide(Ride $ride): self
    {
        return new self(
            rideId: $ride->getKey(),
            driver: [
                'driver_id' => $ride->driver_id,
                'driver_name' => $ride->driver_name,
                'driver_plate' => $ride->driver_plate,
            ],
            position: [
                'lat' => $ride->driver_lat,
                'lng' => $ride->driver_lng,
            ],
        );
    }
}
