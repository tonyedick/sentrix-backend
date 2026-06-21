<?php

declare(strict_types=1);

namespace App\Domains\Rides\Events;

use App\Domains\Rides\Models\Ride;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ride completed (trip finished, fare + tip settled). User-scoped — NOT an
 * OrganizationRecordEvent.
 */
final class RideCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ride $ride,
    ) {}
}
