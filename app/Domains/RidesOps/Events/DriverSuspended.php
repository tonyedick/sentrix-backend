<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Events;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An operator pulled a driver off the road (stage=suspended, availability
 * offline). Plain platform event.
 */
final class DriverSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly Driver $driver,
        public readonly ?string $actorId,
        public readonly string $reason,
    ) {}
}
