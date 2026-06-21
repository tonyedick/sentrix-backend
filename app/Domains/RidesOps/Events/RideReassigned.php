<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Events;

use App\Domains\Rides\Models\Ride;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Manual dispatch override: an operator reassigned a ride to a different driver
 * (the driver snapshot is updated; the real dispatch loop is simulated). Plain
 * platform event.
 */
final class RideReassigned
{
    use Dispatchable;

    public function __construct(
        public readonly Ride $ride,
        public readonly ?string $actorId,
        public readonly string $driverName,
    ) {}
}
