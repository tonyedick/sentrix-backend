<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Events;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An operator reinstated a suspended driver back to stage=active. Plain platform
 * event.
 */
final class DriverReinstated
{
    use Dispatchable;

    public function __construct(
        public readonly Driver $driver,
        public readonly ?string $actorId,
    ) {}
}
