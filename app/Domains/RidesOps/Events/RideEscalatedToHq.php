<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Events;

use App\Domains\Command\Models\CommandIncident;
use App\Domains\Rides\Models\Ride;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A ride was escalated to HQ National Command. The escalation created a parallel
 * CommandIncident (via the Command domain's own routing path), referenced here.
 * Plain platform event.
 */
final class RideEscalatedToHq
{
    use Dispatchable;

    public function __construct(
        public readonly Ride $ride,
        public readonly CommandIncident $incident,
        public readonly ?string $actorId,
    ) {}
}
