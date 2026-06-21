<?php

declare(strict_types=1);

namespace App\Domains\Rides\Events;

use App\Domains\Rides\Models\Ride;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A rider requested a ride and was matched to a (simulated) driver. User-scoped —
 * NOT an OrganizationRecordEvent.
 */
final class RideRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ride $ride,
    ) {}
}
