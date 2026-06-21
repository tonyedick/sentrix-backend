<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Events;

use App\Domains\Rides\Models\Ride;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An operator force-cancelled a ride (safety/abuse intervention). Plain platform
 * event — Rides Ops is platform/staff-scoped, so it carries no org-record
 * broadcast/audit semantics. Other parts of the platform may listen to fan out.
 */
final class RideForceCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Ride $ride,
        public readonly ?string $actorId,
        public readonly string $reason,
    ) {}
}
